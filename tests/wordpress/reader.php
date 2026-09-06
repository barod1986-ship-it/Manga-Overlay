<?php
declare(strict_types=1);

// Included by content.php inside the guarded disposable CI application.
wp_set_current_user($users['manager']);
$reader_work = wp_insert_post(['post_type' => 'mol_work', 'post_title' => 'رحلة بين الصفحات — اختبار القارئ', 'post_name' => 'reader-fixture', 'post_status' => 'publish', 'post_content' => 'قصة مرجعية لاختبار القراءة والترجمة العربية.']);
update_post_meta($reader_work, '_mol_alt_titles', ['Reader alternate unique']);
update_post_meta($reader_work, '_mol_default_reader_mode', 'webtoon');
update_post_meta($reader_work, '_mol_reading_direction', 'rtl');
wp_set_object_terms($reader_work, 'manhwa', 'mol_work_type');
wp_set_object_terms($reader_work, 'en', 'mol_source_language');
$reader_chapter = $runtime->chapter_service->create((object) ['work_id' => $reader_work, 'chapter_label' => '1', 'title' => 'البداية', 'is_published' => true]);
$reader_draft = $runtime->chapter_service->create((object) ['work_id' => $reader_work, 'chapter_label' => '2', 'title' => 'فصل مسودة لا يظهر']);
$reader_ltr = $runtime->chapter_service->create((object) ['work_id' => $reader_work, 'chapter_label' => '3', 'title' => 'اتجاه آخر', 'is_published' => true, 'reader_mode_override' => 'paged', 'direction_override' => 'ltr']);
$reader_image = imagecreatetruecolor(720, 1100);
$paper = imagecolorallocate($reader_image, 249, 245, 235);
$ink = imagecolorallocate($reader_image, 41, 56, 50);
$sky = imagecolorallocate($reader_image, 189, 205, 192);
imagefill($reader_image, 0, 0, $paper);
imagefilledrectangle($reader_image, 24, 24, 695, 510, $sky);
imagefilledrectangle($reader_image, 24, 535, 695, 1075, $ink);
imagefilledellipse($reader_image, 180, 160, 270, 150, $paper);
imagestring($reader_image, 5, 90, 150, 'A NEW CHAPTER', $ink);
imagefilledrectangle($reader_image, 320, 180, 420, 490, $ink);
imagefilledellipse($reader_image, 370, 150, 100, 100, $ink);
imagefilledellipse($reader_image, 465, 740, 390, 190, $paper);
imagestring($reader_image, 5, 325, 735, 'LET US BEGIN THE JOURNEY', $ink);
$reader_fixture = wp_tempnam('mol-reader.png');
imagepng($reader_image, $reader_fixture);
imagedestroy($reader_image);
foreach ([$reader_chapter, $reader_ltr] as $chapter_item) {
	for ($index = 0; $index < 3; ++$index) {
		$copy = wp_tempnam('reader-page.png'); copy($reader_fixture, $copy);
		$response = $runtime->page_service->upload($chapter_item['id'], ['name' => 'reader-page.png', 'tmp_name' => $copy, 'type' => 'image/png', 'size' => filesize($copy), 'error' => UPLOAD_ERR_OK], 'reader-' . $chapter_item['id'] . '-' . $index);
		if ($index === 0) {
			$reader_page_id = $response['data']['id'];
			set_post_thumbnail($reader_work, $response['data']['image']['attachment_id']);
			$now = current_time('mysql', true);
			$wpdb->insert($tables->name('elements'), ['page_id' => $reader_page_id, 'target_lang' => 'ar', 'element_type' => 'bubble', 'content' => 'هنا تبدأ حكايتنا', 'x_unit' => 75000, 'y_unit' => 81000, 'w_unit' => 350000, 'h_unit' => 120000, 'rotation_mdeg' => 0, 'z_index' => 1, 'style_json' => wp_json_encode(['fontId' => 'cairo', 'fontSizeUnit' => 32000, 'fontWeight' => 700, 'color' => '#293832', 'backgroundColor' => '#f9f5eb', 'backgroundOpacity' => 1, 'shape' => 'ellipse', 'paddingUnit' => 10000, 'autoFit' => true]), 'version' => 1, 'created_by' => $users['translator'], 'updated_by' => $users['translator'], 'created_at' => $now, 'updated_at' => $now]);
		}
	}
}
$expect($request('GET', '/chapters/' . $reader_chapter['id'] . '/elements'), 200, 'reader batch matches contract', 'ChapterElementsResponse');
wp_set_current_user($users['member']);
$progress_body = ['chapter_id' => $reader_chapter['id'], 'page_index' => 2, 'progress_unit' => 450000, 'reader_mode' => 'webtoon'];
$expect($request('PUT', '/reading-progress', $progress_body), 200, 'member saves typed reading progress without editor privileges', 'ReadingProgressResponse');
$expect($request('PUT', '/reading-progress', array_merge($progress_body, ['page_index' => 1])), 200, 'progress upsert updates same member chapter', 'ReadingProgressResponse');
$check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE user_id = %d AND chapter_id = %d', $tables->name('reading_progress'), $users['member'], $reader_chapter['id'])) === 1, 'progress is unique per account and chapter');
foreach ([['page_index' => 3], ['page_index' => '1'], ['progress_unit' => 1000001], ['reader_mode' => 'invalid'], ['chapter_id' => $reader_draft['id']], ['chapter_id' => 99999999], ['user_id' => $users['manager']]] as $invalid) {
	$expect($request('PUT', '/reading-progress', array_merge($progress_body, $invalid)), 400, 'invalid/private progress update rejected', 'ErrorResponse');
}
$expect($request('PUT', '/reading-progress', $progress_body, false), 403, 'progress needs nonce for logged-in session', 'ErrorResponse');
wp_set_current_user(0);
$expect($request('PUT', '/reading-progress', $progress_body, false), 401, 'anonymous cannot write account progress', 'ErrorResponse');
wp_set_current_user($users['manager']);
$check((new MOL\Database\ProgressRepository($wpdb))->find($users['manager'], $reader_chapter['id']) === null, 'progress reads never inherit another account state');

$http_fixture += [
	'reader_url' => MOL\Frontend\PublicSite::chapter_url($reader_chapter),
	'reader_ltr_url' => MOL\Frontend\PublicSite::chapter_url($reader_ltr),
	'draft_reader_url' => MOL\Frontend\PublicSite::chapter_url($reader_draft),
	'reader_work_url' => get_permalink($reader_work), 'reader_work_id' => $reader_work,
	'reader_chapter_id' => $reader_chapter['id'], 'reader_draft_id' => $reader_draft['id'],
];
