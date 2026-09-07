<?php
declare(strict_types=1);

// Included by the disposable content harness; shares its guarded database, users and captures.
use MOL\Domain\ElementInput;
use MOL\Domain\Fault;

wp_set_current_user($users['manager']);
$element_chapter = $runtime->chapter_service->create((object) ['work_id' => $work, 'chapter_label' => 'Element writes']);
$element_page = $runtime->pages->create($element_chapter['id'], $attachment_id ?? $first['data']['image']['attachment_id'], 32, 48);
$element_request = static function (string $method, string $route, mixed $body = null, array $headers = []): WP_REST_Response {
    $req = new WP_REST_Request($method, '/mol/v1' . $route);
    $req->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    foreach ($headers as $key => $value) { $req->set_header($key, $value); }
    if ($body !== null) { $req->set_header('Content-Type', 'application/json'); $req->set_body(wp_json_encode($body)); }
    return rest_do_request($req);
};
$base_element = ['page_id' => (int) $element_page['id'], 'target_lang' => 'ar', 'element_type' => 'bubble', 'x_unit' => 0, 'y_unit' => 0, 'w_unit' => 100000, 'h_unit' => 100000, 'content' => 'مرحبا'];
$create = static fn (array $body, string $key = '') => $element_request('POST', '/elements', (object) $body, ['MOL-Idempotency-Key' => $key]);
wp_set_current_user(0);
$expect($create($base_element, 'guest'), 401, 'guest cannot create element', 'ErrorResponse');
wp_set_current_user($users['member']);
$expect($create($base_element, 'member'), 403, 'member cannot create element', 'ErrorResponse');
wp_set_current_user($users['translator']);
$expect($create($base_element), 400, 'element creation requires retry key', 'ErrorResponse');
$expect($create($base_element + ['unexpected' => true], 'invalid'), 400, 'element create closes allOf fields', 'ErrorResponse');
$expect($create(array_replace($base_element, ['x_unit' => 950000]), 'bounds'), 400, 'combined geometry stays inside image', 'ErrorResponse');
$expect($create(array_replace($base_element, ['page_id' => PHP_INT_MAX]), 'missing'), 400, 'element parent must exist', 'ErrorResponse');
$one = $create($base_element, 'first-element');
$saved = $expect($one, 201, 'create resolved element', 'ElementResponse')['data'];
$id = $saved['id'];
$check(($one->get_headers()['ETag'] ?? null) === '"1"' && $saved['version'] === 1 && $saved['style']->fontId === 'cairo', 'strong ETag and resolved base style');
$check($expect($create($base_element, 'first-element'), 201, 'safe element replay', 'ElementResponse')['data'] == $saved, 'element replay preserves identity and response');
$expect($create(array_replace($base_element, ['content' => 'different']), 'first-element'), 409, 'element retry hash mismatch', 'ErrorResponse');
$check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE element_id = %d', $tables->name('contributions'), $id)) === 1, 'create replay does not duplicate contribution');
$check($wpdb->get_var($wpdb->prepare('SELECT resource_type FROM %i WHERE resource_id = %d AND scope = %s', $tables->name('idempotency_keys'), $id, 'element:create')) === 'element', 'retry journal records element resource type');
$path = '/elements/' . $id;
$expect($element_request('PATCH', $path, (object) ['content' => 'without lease'], ['If-Match' => '"1"']), 423, 'writes require active owner lease', 'ErrorResponse');
$lease = $expect($element_request('POST', $path . '/lock'), 200, 'acquire element lease', 'LockLeaseResponse')['data'];
$token = $lease['lock_token'];
$check(strlen($token) === 64 && strtotime($lease['expires_at']) - time() >= 43 && strtotime($lease['expires_at']) - time() <= 45, 'random 256-bit token and 45 second lease');
$headers = ['X-MOL-Lock-Token' => $token, 'If-Match' => '"1"'];
$expect($element_request('PATCH', $path, (object) ['content' => 'no version'], ['X-MOL-Lock-Token' => $token]), 428, 'missing If-Match rejected', 'ErrorResponse');
foreach ([new stdClass(), (object) ['element_type' => 'bubble'], (object) ['element_type' => 'narration', 'style' => new stdClass()], (object) ['style' => (object) ['color' => '#FFFFFF']], (object) ['element_type' => 'bubble', 'style' => (object) ['tail' => (object) ['evil' => 1]]], (object) ['element_type' => 'bubble', 'style' => (object) ['burst' => null]], (object) ['element_type' => 'bubble', 'style' => (object) ['color' => 'url(javascript:alert(1))']]] as $invalid_patch) {
    $expect($element_request('PATCH', $path, $invalid_patch, $headers), 400, 'strict immutable type and style patch', 'ErrorResponse');
}
$expect($element_request('PATCH', $path, (object) ['content' => 'stale'], array_replace($headers, ['If-Match' => '"99"'])), 412, 'stale version rejected', 'ErrorResponse');
$expect($element_request('PATCH', $path, (object) ['content' => 'weak'], array_replace($headers, ['If-Match' => 'W/"1"'])), 412, 'weak version rejected', 'ErrorResponse');
$changed = $element_request('PATCH', $path, (object) ['content' => "نص\nجديد", 'element_type' => 'bubble', 'style' => (object) ['tail' => (object) ['enabled' => true, 'lengthUnit' => 12000]]], $headers);
$current = $expect($changed, 200, 'save under lease and version', 'ElementResponse')['data'];
$check($current['version'] === 2 && $changed->get_headers()['ETag'] === '"2"' && $current['created_by'] === $users['translator'], 'version increments once, attribution preserved');
$headers['If-Match'] = '"2"';
$second = $expect($element_request('PATCH', $path, (object) ['element_type' => 'bubble', 'style' => (object) ['tail' => (object) ['angleMdeg' => 90000]]], $headers), 200, 'nested partial style merge', 'ElementResponse')['data'];
$check($second['style']->tail->lengthUnit === 12000 && $second['style']->tail->angleMdeg === 90000, 'nested merge retains siblings');
$check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE element_id = %d', $tables->name('contributions'), $id)) === 1, 'autosaves count unique contributed element once');
wp_set_current_user($users['manager']);
$locked = $expect($element_request('POST', $path . '/lock'), 423, 'second editor cannot acquire occupied lease', 'ErrorResponse');
$check(!str_contains(wp_json_encode($locked), $token), 'another user never receives lease token');
$expect($element_request('PUT', $path . '/lock', null, ['X-MOL-Lock-Token' => $token]), 409, 'renew requires token and owner', 'ErrorResponse');
$expect($element_request('DELETE', $path . '/lock'), 204, 'manager force release uses canonical route');
wp_set_current_user($users['translator']);
$expect($element_request('PUT', $path . '/lock', null, ['X-MOL-Lock-Token' => $token]), 409, 'removed lease cannot renew', 'ErrorResponse');
$expect($element_request('DELETE', $path . '/lock', null, ['X-MOL-Lock-Token' => $token]), 409, 'removed lease cannot release', 'ErrorResponse');
$lease = $expect($element_request('POST', $path . '/lock'), 200, 'reacquire after force release', 'LockLeaseResponse')['data'];
$check($lease['lock_token'] !== $token, 'reacquired lease uses fresh token');
$token = $lease['lock_token'];
$expect($element_request('PUT', $path . '/lock', null, ['X-MOL-Lock-Token' => $token]), 200, 'owner renews lease', 'LockLeaseResponse');
$wpdb->update($tables->name('element_locks'), ['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)], ['element_id' => $id]);
$expect($element_request('PUT', $path . '/lock', null, ['X-MOL-Lock-Token' => $token]), 409, 'expired lease cannot renew', 'ErrorResponse');
wp_set_current_user($users['manager']);
$lease = $expect($element_request('POST', $path . '/lock'), 200, 'another editor acquires expired lease', 'LockLeaseResponse')['data'];
$headers = ['X-MOL-Lock-Token' => $lease['lock_token'], 'If-Match' => '"3"'];
$changed = $expect($element_request('PATCH', $path, (object) ['content' => 'مراجعة'], $headers), 200, 'second author contributes', 'ElementResponse')['data'];
$check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE element_id = %d', $tables->name('contributions'), $id)) === 2, 'distinct authors count once each');
$headers['If-Match'] = '"4"';
$wpdb->insert($tables->name('reports'), ['chapter_id' => $element_chapter['id'], 'page_id' => $element_page['id'], 'element_id' => $id, 'reporter_id' => $users['member'], 'report_type' => 'translation', 'message' => 'fixture', 'created_at' => current_time('mysql', true)]);
$element_report = $wpdb->insert_id;
$expect($element_request('DELETE', $path, null, $headers), 204, 'delete element under version and lease');
foreach (['elements' => 'id', 'element_locks' => 'element_id', 'contributions' => 'element_id'] as $table => $column) {
    $check(!(bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE %i = %d', $tables->name($table), $column, $id)), 'element delete cascades ' . $table);
}
$check($wpdb->get_var($wpdb->prepare('SELECT element_id FROM %i WHERE id = %d', $tables->name('reports'), $element_report)) === null, 'element delete preserves report without dangling reference');
foreach (['POST', 'PUT', 'DELETE'] as $method) { $expect($element_request($method, $path . '/lock'), 404, 'missing element lock route ' . $method, 'ErrorResponse'); }

// Preset resolution is required for element creation even before preset management UI is built.
$presets = [];
foreach (['global', 'work', 'personal'] as $scope) {
    $wpdb->insert($tables->name('style_presets'), ['scope' => $scope, 'work_id' => $scope === 'work' ? $work : null, 'owner_user_id' => $scope === 'personal' ? $users['manager'] : null, 'name' => $scope, 'element_type' => 'bubble', 'style_json' => wp_json_encode(['color' => ['global' => '#111122', 'work' => '#111133', 'personal' => '#111144'][$scope]]), 'is_default' => 1, 'created_by' => $users['manager'], 'created_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)]);
    $presets[$scope] = (int) $wpdb->insert_id;
}
$check($expect($create($base_element, 'personal-default'), 201, 'resolve personal default', 'ElementResponse')['data']['style']->color === '#111144', 'personal wins default precedence');
$check($expect($create($base_element + ['preset_id' => $presets['global'], 'style' => (object) ['fontWeight' => 900]], 'explicit-default'), 201, 'resolve explicit preset with override', 'ElementResponse')['data']['style']->color === '#111122', 'explicit preset wins default precedence');
foreach ($presets as $preset_id) { $wpdb->delete($tables->name('style_presets'), ['id' => $preset_id]); }

// Failure after INSERT must roll back the element and idempotency journal together.
$count_before = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tables->name('elements'));
$broken = static fn ($sql) => str_starts_with($sql, 'INSERT INTO `' . $tables->name('contributions') . '`') ? 'INSERT INTO mol_missing_fixture_table VALUES (1)' : $sql;
add_filter('query', $broken);
$prior_errors = $wpdb->suppress_errors();
try { $expect($create($base_element, 'transaction-rollback'), 500, 'contribution failure aborts save'); }
finally { remove_filter('query', $broken); $wpdb->suppress_errors($prior_errors); }
$check((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tables->name('elements')) === $count_before, 'failed contribution rolls back element');
$expect($create($base_element, 'transaction-rollback'), 201, 'retry after rollback creates element exactly once', 'ElementResponse');

update_option('mol_element_writes_per_minute', 1);
wp_cache_flush();
$limited = $create($base_element, 'limited');
$expect($limited, 429, 'element limiter persists beyond cache eviction', 'ErrorResponse');
$check((int) ($limited->get_headers()['Retry-After'] ?? 0) > 0, 'write limiter includes retry header');
delete_option('mol_element_writes_per_minute');
update_option('mol_lock_acquires_per_minute', 1);
$expect($element_request('POST', $path . '/lock'), 429, 'lock acquisition has enforceable limiter', 'ErrorResponse');
$expect($element_request('PUT', $path . '/lock'), 404, 'lock renew has no acquisition rate limit', 'ErrorResponse');
delete_option('mol_lock_acquires_per_minute');
$runtime->page_service->delete_chapter($element_chapter['id']);

$vector_count = 0;
foreach (json_decode(file_get_contents(getenv('MOL_ELEMENT_VECTORS')), false, 512, JSON_THROW_ON_ERROR) as $case) {
    $valid = true;
    try { ElementInput::validate($case->schema, $case->value); } catch (Fault $error) { $valid = false; }
    if ($valid !== $case->valid) { throw new RuntimeException('Element schema evaluator differs from independent validator: ' . wp_json_encode($case)); }
    ++$vector_count;
}
$check($vector_count > 1000, $vector_count . ' independent element schema vectors agree');
