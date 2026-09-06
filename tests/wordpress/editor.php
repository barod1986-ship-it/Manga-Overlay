<?php
declare(strict_types=1);

// Included in the disposable content test after reader.php, never in production.
use MOL\Frontend\EditorSite;
use MOL\Frontend\PublicSite;

wp_set_current_user($users['manager']);
$editor_empty = $runtime->chapter_service->create((object) ['work_id' => $reader_work, 'chapter_label' => '4', 'title' => 'مسودة خالية']);
$wide = imagecreatetruecolor(960, 420);
imagefill($wide, 0, 0, imagecolorallocate($wide, 243, 236, 215));
$wide_path = wp_tempnam('mol-editor-wide.png'); imagepng($wide, $wide_path); imagedestroy($wide);
$editor_pages = [];
foreach ([$reader_fixture, $wide_path] as $index => $source) {
	$copy = wp_tempnam('editor-page.png'); copy($source, $copy);
	$editor_pages[] = $runtime->page_service->upload($reader_draft['id'], ['name' => 'editor-page.png', 'tmp_name' => $copy, 'type' => 'image/png', 'size' => filesize($copy), 'error' => UPLOAD_ERR_OK], 'editor-' . $index)['data'];
}
$editor_elements = [];
foreach (['bubble', 'narration', 'free_text', 'sfx'] as $index => $type) {
	$wpdb->insert($tables->name('elements'), [
		'page_id' => $editor_pages[0]['id'], 'target_lang' => 'ar', 'element_type' => $type,
		'content' => $index === 0 ? 'نص خاص داخل المحرر' : 'عنصر ' . $type,
		'x_unit' => 80000, 'y_unit' => 80000 + $index * 180000, 'w_unit' => 700000, 'h_unit' => 150000,
		'rotation_mdeg' => 0, 'z_index' => $index, 'style_json' => wp_json_encode(['fontId' => 'cairo', 'fontSizeUnit' => 26000, 'color' => '#111111', 'backgroundColor' => '#FFFFFF', 'backgroundOpacity' => .9, 'shape' => ['ellipse', 'rounded_rect', 'none', 'burst'][$index]]),
		'version' => 7, 'created_by' => $users['translator'], 'updated_by' => $users['translator'], 'created_at' => $now, 'updated_at' => $now,
	]);
	$editor_elements[] = (int) $wpdb->insert_id;
}
$wpdb->insert($tables->name('elements'), [
	'page_id' => $editor_pages[1]['id'], 'target_lang' => 'ar', 'element_type' => 'free_text', 'content' => 'الصفحة العريضة',
	'x_unit' => 100000, 'y_unit' => 100000, 'w_unit' => 700000, 'h_unit' => 300000, 'rotation_mdeg' => 0, 'z_index' => 0, 'style_json' => '{}',
	'version' => 1, 'created_by' => $users['translator'], 'updated_by' => $users['translator'], 'created_at' => $now, 'updated_at' => $now,
]);
$work_slug = get_post_field('post_name', $reader_work);
wp_set_current_user(0);
$fault(static fn () => EditorSite::context($work_slug, $reader_draft['slug']), 'mol_not_authenticated');
wp_set_current_user($users['member']);
$fault(static fn () => EditorSite::context($work_slug, $reader_draft['slug']), 'mol_forbidden');
$fault(static fn () => EditorSite::context('nonexistent', 'nonexistent'), 'mol_forbidden');
$member = new WP_User($users['member']);
$member->add_cap('mol_use_editor');
wp_set_current_user(0); wp_set_current_user($users['member']);
$context = EditorSite::context($work_slug, $reader_draft['slug']);
$check($context['chapterId'] === $reader_draft['id'], 'editor shell checks individual capability, not role name or edit capability');
$check(!isset($context['pages'], $context['elements'], $context['chapter']) && !str_contains(wp_json_encode($context, JSON_UNESCAPED_UNICODE), 'نص خاص داخل المحرر'), 'shell bootstrap contains no draft page or overlay payload');
$check($context['canEdit'] === false && $context['canDelete'] === false, 'shell-only grant does not enable editing or deletion');
$check((bool) wp_verify_nonce($context['nonce'], 'wp_rest'), 'editor bootstrap nonce belongs to the current session');
$expect($request('GET', '/pages/' . $editor_pages[0]['id'] . '/elements'), 200, 'editor retrieves draft elements only via authenticated REST', 'PageElementsResponse');
$check(mol_get_chapter($reader_draft['id']) === null, 'public PHP reader still hides draft from shell user');
$member->remove_cap('mol_use_editor');
wp_set_current_user(0); wp_set_current_user($users['member']);
$fault(static fn () => EditorSite::context($work_slug, $reader_draft['slug']), 'mol_forbidden');
$expect($request('GET', '/pages/' . $editor_pages[0]['id'] . '/elements'), 404, 'revoked editor capability closes draft REST access', 'ErrorResponse');
wp_set_current_user($users['translator']);
$fault(static fn () => EditorSite::context($work_slug, 'nonexistent'), 'mol_not_found');
$translator_context = EditorSite::context($work_slug, $reader_draft['slug']);
$check($translator_context['chapterId'] === $reader_draft['id'], 'translator opens editor shell for draft chapter');
$check($translator_context['canEdit'] && $translator_context['canDelete'], 'translator receives independent editing and deletion grants');
wp_update_post(['ID' => $reader_work, 'post_status' => 'draft']);
$check(EditorSite::context($work_slug, $reader_draft['slug'])['chapterId'] === $reader_draft['id'], 'authorized editor shell can resolve an unpublished parent work');
$check(mol_get_chapter($reader_chapter['id']) === null, 'unpublished parent remains hidden from public reader');
wp_update_post(['ID' => $reader_work, 'post_status' => 'publish']);
$expect($request('GET', '/chapters/' . $reader_draft['id'] . '/pages'), 200, 'editor portrait and landscape pages match DTO', 'PageListResponse');
MOL\Frontend\EditorSite::$context = $context;
$check(MOL\Frontend\Routes::template('/arbitrary-theme/index.php') === dirname(__DIR__, 2) . '/wp-content/plugins/manga-overlay-core/templates/editor.php', 'editor template belongs to plugin independently of active theme');
MOL\Frontend\EditorSite::$context = null;

$editor_password = wp_generate_password(40, false);
$member_password = wp_generate_password(40, false);
wp_set_password($editor_password, $users['translator']);
wp_set_password($member_password, $users['member']);
$http_fixture += [
	'editor_username' => 'mol_content_translator', 'editor_password' => $editor_password,
	'member_username' => 'mol_content_member', 'member_password' => $member_password,
	'editor_url' => PublicSite::chapter_url($reader_draft) . 'edit/',
	'editor_empty_url' => PublicSite::chapter_url($editor_empty) . 'edit/',
	'editor_page_ids' => array_column($editor_pages, 'id'), 'editor_element_ids' => $editor_elements,
];
wp_set_current_user($users['manager']);

// Independent grants exercise the connected UI without relying on translator role names.
foreach (['viewer', 'writer'] as $kind) {
    $password = wp_generate_password(40, false);
    $id = wp_insert_user(['user_login' => 'mol_editor_' . $kind, 'user_pass' => $password, 'role' => 'mol_member']);
    if (is_wp_error($id)) { throw new RuntimeException('Could not create editor permission fixture.'); }
    $user = new WP_User($id);
    $user->add_cap('mol_use_editor');
    if ($kind === 'writer') { $user->add_cap('mol_edit_translations'); }
    wp_set_current_user($id);
    $grants = EditorSite::context($work_slug, $reader_draft['slug']);
    $check($grants['canEdit'] === ($kind === 'writer') && $grants['canDelete'] === false, 'individual ' . $kind . ' grants separate shell, edit and delete');
    $http_fixture['editor_' . $kind . '_username'] = 'mol_editor_' . $kind;
    $http_fixture['editor_' . $kind . '_password'] = $password;
}
wp_set_current_user($users['manager']);
