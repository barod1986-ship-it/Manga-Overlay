<?php
declare(strict_types=1);

use MOL\Database\PageRepository;
use MOL\Database\RateLimitRepository;
use MOL\Database\Tables;
use MOL\Domain\Fault;
use MOL\Media\MediaService;
use MOL\Security\ChapterVisibilityPolicy;
use MOL\Security\RateLimiter;
use MOL\Services\ContentRuntime;

if (!defined('WP_CLI') || !WP_CLI || getenv('MOL_TEST_ENV') !== '1' || wp_get_environment_type() !== 'local') {
	throw new RuntimeException('Disposable CI environment required.');
}
global $wpdb;
$checks = 0;
$samples = [];
$check = static function (bool $value, string $label) use (&$checks): void {
	if (!$value) {
		throw new RuntimeException($label);
	}
	++$checks;
	WP_CLI::log('PASS ' . $label);
};
$sample = static function (string $schema, mixed $body) use (&$samples): void {
	$samples[] = ['schema' => $schema, 'body' => $body];
};
$fault = static function (callable $operation, string $code) use ($check): void {
	try {
		$operation();
	} catch (Fault $error) {
		$check($error->error_code === $code, 'domain rejection: ' . $code);
		return;
	}
	$check(false, 'expected ' . $code);
};
$request = static function (string $method, string $path, mixed $body = null, bool $nonce = true): WP_REST_Response {
	$request = new WP_REST_Request($method, '/mol/v1' . $path);
	if ($nonce) {
		$request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
	}
	if ($body !== null) {
		$request->set_header('Content-Type', 'application/json');
		$request->set_body(wp_json_encode($body));
	}
	return rest_do_request($request);
};
$expect = static function (WP_REST_Response $response, int $status, string $label, ?string $schema = null) use ($check, $sample): array {
	$data = $response->get_data();
	$check($response->get_status() === $status, $label . ' (' . $response->get_status() . ($response->get_status() >= 400 ? ' ' . wp_json_encode($data) : '') . ')');
	if ($schema) {
		$sample($schema, $data);
	}
	return $data;
};
$runtime = new ContentRuntime($wpdb);
$tables = new Tables($wpdb);
$users = [];
foreach (['manager' => 'mol_manager', 'translator' => 'mol_translator', 'moderator' => 'mol_moderator', 'uploader' => 'mol_member', 'member' => 'mol_member'] as $key => $role) {
	$id = wp_insert_user(['user_login' => 'mol_content_' . $key, 'user_pass' => wp_generate_password(32), 'role' => $role]);
	$check(!is_wp_error($id), 'create content test user ' . $key);
	$users[$key] = (int) $id;
}
(new WP_User($users['uploader']))->add_cap('mol_upload_content');
wp_set_current_user($users['manager']);
$work = wp_insert_post(['post_type' => 'mol_work', 'post_title' => 'كتاب اختبار المحتوى', 'post_status' => 'publish']);
$draft_body = ['work_id' => $work, 'chapter_label' => '10.5', 'sort_order' => 10.5];
$draft = $expect($request('POST', '/chapters', $draft_body), 201, 'manager creates draft', 'ChapterResponse')['data'];
$check($draft['sort_order'] === 10.5 && $draft['is_published'] === false, 'chapter numeric and boolean DTO values');
$same = $expect($request('POST', '/chapters', $draft_body), 201, 'slug collision retries', 'ChapterResponse')['data'];
$check($same['slug'] === $draft['slug'] . '-2', 'second chapter receives canonical suffix');
$published = $expect($request('POST', '/chapters', ['work_id' => $work, 'chapter_label' => '11', 'is_published' => true]), 201, 'create published chapter', 'ChapterResponse')['data'];
$check($published['published_at'] !== null, 'published creation has UTC timestamp');
$expect($request('POST', '/chapters', $draft_body + ['slug' => 'client-value']), 400, 'client slug rejected', 'ErrorResponse');
$expect($request('POST', '/chapters', $draft_body + ['unexpected' => true]), 400, 'unknown chapter property rejected', 'ErrorResponse');
$expect($request('POST', '/chapters', ['work_id' => 99999999, 'chapter_label' => '1']), 400, 'missing work rejected', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'], new stdClass()), 400, 'empty chapter patch rejected', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'], ['sort_order' => '1.5']), 400, 'numeric string rejected', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'], ['sort_order' => 10000000000]), 400, 'DECIMAL overflow rejected', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'], ['translation_status' => 'invented']), 400, 'unknown status rejected', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'], ['direction_override' => 'ltr', 'reader_mode_override' => 'paged']), 200, 'reader overrides update', 'ChapterResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'], ['direction_override' => null, 'reader_mode_override' => null]), 200, 'reader overrides clear', 'ChapterResponse');
$expect($request('POST', '/chapters', $draft_body, false), 403, 'logged-in user without nonce cannot write', 'ErrorResponse');
wp_set_current_user(0);
$expect($request('POST', '/chapters', $draft_body, false), 401, 'anonymous cannot create chapters', 'ErrorResponse');
wp_set_current_user($users['uploader']);
$expect($request('POST', '/chapters', $draft_body), 403, 'upload grant does not grant chapter management', 'ErrorResponse');
wp_set_current_user($users['moderator']);
$expect($request('PATCH', '/chapters/' . $draft['id'], ['title' => 'forbidden']), 403, 'review role cannot alter general chapter fields', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'] . '/review', ['translation_status' => 'completed']), 200, 'review role uses dedicated route', 'ChapterResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'] . '/review', ['translation_status' => 'untranslated']), 400, 'review route restricts states', 'ErrorResponse');
$expect($request('PATCH', '/chapters/' . $draft['id'] . '/review', ['translation_status' => 'needs_review', 'title' => 'forbidden']), 400, 'review cannot smuggle content changes', 'ErrorResponse');

$image = imagecreatetruecolor(32, 48);
$fixture = wp_tempnam('mol-source.png');
imagepng($image, $fixture);
imagedestroy($image);
$file = static function (string $name = 'page.png') use ($fixture): array {
	$copy = wp_tempnam($name);
	copy($fixture, $copy);
	return ['name' => $name, 'tmp_name' => $copy, 'type' => 'image/png', 'size' => filesize($copy), 'error' => UPLOAD_ERR_OK];
};
wp_set_current_user($users['uploader']);
$first = $runtime->page_service->upload($draft['id'], $file(), 'retry-one');
$sample('PageResponse', $first);
$retry = $runtime->page_service->upload($draft['id'], $file(), 'retry-one');
$sample('PageResponse', $retry);
$check($first['data']['id'] === $retry['data']['id'] && count($runtime->pages->for_chapter($draft['id'])) === 1, 'upload retry returns one attachment/page');
$check(is_file(get_attached_file($first['data']['image']['attachment_id'])), 'WordPress owns the original attachment');
$check(wp_delete_attachment($first['data']['image']['attachment_id'], true) === false, 'referenced attachment cannot be deleted through core media API');
$fault(static fn () => $runtime->page_service->upload($draft['id'], $file('different-name.png'), 'retry-one'), 'mol_idempotency_mismatch');
$case = $runtime->page_service->upload($draft['id'], $file(), 'RETRY-ONE');
$check($case['data']['id'] !== $first['data']['id'], 'idempotency header identity is case-sensitive');
$second = $runtime->page_service->upload($draft['id'], $file('2.png'), 'second-key');
$third = $runtime->page_service->upload($draft['id'], $file('3.png'), 'third-key');
$fault(static fn () => $runtime->page_service->upload($draft['id'], $file(), ''), 'mol_invalid_params');
$fault(static fn () => $runtime->page_service->upload($draft['id'], $file(), str_repeat('x', 101)), 'mol_invalid_params');
$fault(static fn () => $runtime->page_service->upload(999999999, $file(), 'missing-chapter'), 'mol_not_found');
update_option('mol_upload_max_bytes', 1);
$fault(static fn () => $runtime->page_service->upload($draft['id'], $file(), 'too-large'), 'mol_payload_too_large');
delete_option('mol_upload_max_bytes');
update_option('mol_image_max_pixels', 1);
$fault(static fn () => $runtime->page_service->upload($draft['id'], $file(), 'too-many-pixels'), 'mol_payload_too_large');
delete_option('mol_image_max_pixels');
$bad = $file('not-an-image.jpg');
file_put_contents($bad['tmp_name'], '<svg onload="alert(1)"></svg>');
$fault(static fn () => $runtime->page_service->upload($draft['id'], $bad, 'bad-image'), 'mol_unsupported_media');
$large_path = wp_tempnam('mol-large.png');
$large_image = imagecreatetruecolor(1200, 1800);
imagepng($large_image, $large_path);
imagedestroy($large_image);
$large_file = ['name' => 'large.png', 'tmp_name' => $large_path, 'type' => 'image/png', 'error' => UPLOAD_ERR_OK, 'size' => filesize($large_path)];
$media = new MediaService();
$large_info = $media->inspect($large_file);
$original_hash = hash_file('sha256', $large_path);
$created = $media->create($large_file, $large_info, $work);
$metadata = wp_get_attachment_metadata($created['attachment_id']);
$check(hash_file('sha256', get_attached_file($created['attachment_id'])) === $original_hash, 'derivatives preserve original image bytes');
$check(count($metadata['sizes'] ?? []) >= 3, 'responsive derivatives are generated');
foreach ($metadata['sizes'] as $size) {
	$check($size['width'] < 1200, 'responsive derivative never upscales');
	if (wp_image_editor_supports(['mime_type' => 'image/webp'])) {
		$check($size['mime-type'] === 'image/webp', 'WebP conversion is explicit in the MOL pipeline');
	}
}
$media->cleanup($created);
wp_set_current_user($users['member']);
$fault(static fn () => $runtime->page_service->upload($draft['id'], $file(), 'forbidden-upload'), 'mol_forbidden');

$page_id = $first['data']['id'];
$private_routes = [
	'/chapters/' . $draft['id'], '/chapters/' . $draft['id'] . '/pages', '/chapters/' . $draft['id'] . '/elements',
	'/chapters/' . $draft['id'] . '/contributors', '/pages/' . $page_id . '/elements',
];
foreach ([0, $users['member'], $users['uploader']] as $user_id) {
	wp_set_current_user($user_id);
	foreach ($private_routes as $route) {
		$expect($request('GET', $route), 404, 'draft hidden from unauthorized caller ' . $user_id . ' ' . $route, 'ErrorResponse');
	}
}
wp_set_current_user($users['translator']);
foreach ($private_routes as $route) {
	$expect($request('GET', $route), 200, 'authenticated editor sees draft resource ' . $route);
	$expect($request('GET', $route, null, false), 404, 'nonce-less draft lookup hidden ' . $route, 'ErrorResponse');
}
$fault(static fn () => ChapterVisibilityPolicy::require($draft), 'mol_not_found');
$listed = $expect($request('GET', '/works/' . $work . '/chapters'), 200, 'work collection always omits drafts', 'ChapterListResponse');
$check(array_column($listed['data'], 'id') === [$published['id']], 'authenticated collection contains only published chapters');
$expect($request('GET', '/works/99999999/chapters'), 404, 'missing work collection', 'ErrorResponse');
wp_set_current_user(0);
$expect($request('GET', '/chapters/' . $published['id'], null, false), 200, 'published chapter public', 'ChapterResponse');
$expect($request('GET', '/capabilities', null, false), 200, 'runtime capabilities public', 'CapabilitiesResponse');

wp_set_current_user($users['manager']);
$all_ids = array_map('intval', array_column($runtime->pages->for_chapter($draft['id']), 'id'));
$swap = $all_ids; [$swap[0], $swap[1]] = [$swap[1], $swap[0]];
foreach ([$swap, array_reverse($all_ids), [$all_ids[2], $all_ids[0], $all_ids[3], $all_ids[1]]] as $order) {
	$reordered = $expect($request('PATCH', '/chapters/' . $draft['id'] . '/pages/reorder', ['page_ids' => $order]), 200, 'two-phase collision-safe reorder', 'PageListResponse')['data'];
	$check(array_column($reordered, 'id') === $order && array_column($reordered, 'page_index') === [0, 1, 2, 3], 'all page indexes normalized after reorder');
}
$before = $runtime->pages->for_chapter($draft['id']);
foreach ([[$all_ids[0]], [$all_ids[0], $all_ids[0], $all_ids[2], $all_ids[3]], [...$all_ids, 99999999], []] as $order) {
	$error = $expect($request('PATCH', '/chapters/' . $draft['id'] . '/pages/reorder', ['page_ids' => $order]), 400, 'invalid permutation rejected', 'ErrorResponse');
	$check($error['code'] === 'mol_invalid_reorder', 'canonical reorder error');
	$check($runtime->pages->for_chapter($draft['id']) === $before, 'failed reorder preserves all indexes');
}
$expect($request('PATCH', '/chapters/' . $draft['id'] . '/pages/reorder', ['page_ids' => $all_ids, 'extra' => 1]), 400, 'reorder is a closed body', 'ErrorResponse');
wp_set_current_user($users['uploader']);
$expect($request('PATCH', '/chapters/' . $draft['id'] . '/pages/reorder', ['page_ids' => $all_ids]), 403, 'upload alone cannot reorder', 'ErrorResponse');

wp_set_current_user($users['manager']);
$now = current_time('mysql', true);
$wpdb->insert($tables->name('elements'), [
	'page_id' => $page_id, 'target_lang' => 'ar', 'element_type' => 'free_text', 'x_unit' => 0, 'y_unit' => 0, 'w_unit' => 100000, 'h_unit' => 100000,
	'content' => 'نص عربي <script>plain text</script>', 'style_json' => '{}', 'created_by' => $users['translator'], 'updated_by' => $users['translator'], 'created_at' => $now, 'updated_at' => $now,
]);
$element_id = (int) $wpdb->insert_id;
$wpdb->insert($tables->name('element_locks'), ['element_id' => $element_id, 'user_id' => $users['translator'], 'lock_token' => bin2hex(random_bytes(32)), 'acquired_at' => $now, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 45)]);
$wpdb->insert($tables->name('contributions'), ['element_id' => $element_id, 'user_id' => $users['translator'], 'work_id' => $work, 'chapter_id' => $draft['id'], 'first_contributed_at' => $now, 'last_contributed_at' => $now]);
$wpdb->insert($tables->name('reports'), ['chapter_id' => $draft['id'], 'page_id' => $page_id, 'element_id' => $element_id, 'reporter_id' => $users['member'], 'report_type' => 'translation', 'message' => 'test report', 'created_at' => $now]);
$report_id = (int) $wpdb->insert_id;
$wpdb->insert($tables->name('reading_progress'), ['user_id' => $users['member'], 'chapter_id' => $draft['id'], 'page_index' => 3, 'progress_unit' => 500000, 'updated_at' => $now]);
$bundle = $expect($request('GET', '/chapters/' . $draft['id'] . '/elements'), 200, 'chapter overlay fetched as one bundle', 'ChapterElementsResponse');
$check($bundle['meta']->element_count === 1 && $bundle['meta']->page_count === 4, 'bundle includes empty pages and correct counts');
$expect($request('GET', '/pages/' . $page_id . '/elements'), 200, 'page overlay typed response', 'PageElementsResponse');
$expect($request('GET', '/chapters/' . $draft['id'] . '/contributors'), 200, 'contributors use unique element attribution', 'ContributorListResponse');
$original_path = get_attached_file($first['data']['image']['attachment_id']);
$expect($request('DELETE', '/pages/' . $page_id), 204, 'delete page cascades dependent rows');
foreach (['elements' => 'id', 'element_locks' => 'element_id', 'contributions' => 'element_id'] as $table => $column) {
	$check(!(bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE %i = %d', $tables->name($table), $column, $element_id)), 'delete page clears ' . $table);
}
$report = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', $tables->name('reports'), $report_id), ARRAY_A);
$check($report['page_id'] === null && $report['element_id'] === null, 'retained report no longer references deleted descendants');
$check(is_file($original_path), 'page deletion retains original media');
$check(array_map('intval', array_column($runtime->pages->for_chapter($draft['id']), 'page_index')) === [0, 1, 2], 'page deletion compacts indexes');
$expect($request('DELETE', '/chapters/' . $draft['id']), 204, 'chapter deletion cascades its content');
foreach (['pages', 'reports', 'reading_progress', 'contributions'] as $table) {
	$check(!(bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE chapter_id = %d', $tables->name($table), $draft['id'])), 'chapter deletion clears ' . $table);
}
$expect($request('GET', '/chapters/' . $draft['id']), 404, 'deleted chapter unavailable', 'ErrorResponse');

wp_set_current_user($users['member']);
update_option('mol_uploads_per_minute', 1);
$limiter = new RateLimiter(new RateLimitRepository($wpdb));
$limiter->upload();
wp_cache_flush();
$fault(static fn () => $limiter->upload(), 'mol_rate_limited');
delete_option('mol_uploads_per_minute');
$check((bool) wp_next_scheduled('mol_cleanup_temporary_data'), 'temporary data cleanup is scheduled');

// Credentials are written only into the disposable runner temp directory, never an artifact.
$password = wp_generate_password(40, false);
wp_set_password($password, $users['manager']);
$http_fixture = ['username' => 'mol_content_manager', 'password' => $password, 'chapter_id' => $published['id'], 'work_id' => $work, 'image_path' => $fixture];
$fixture_path = getenv('MOL_HTTP_FIXTURE');
$samples_path = getenv('MOL_CONTENT_RESPONSES');
if (!$fixture_path || !$samples_path) {
	throw new RuntimeException('CI output paths required.');
}
file_put_contents($fixture_path, wp_json_encode($http_fixture));
chmod($fixture_path, 0600);
file_put_contents($samples_path, wp_json_encode($samples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
WP_CLI::success($checks . ' content integration checks passed.');
