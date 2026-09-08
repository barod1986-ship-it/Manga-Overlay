<?php
declare(strict_types=1);

// Included only by content.php in the disposable CI application.
update_option('mol_reports_per_minute', 1000);
wp_set_current_user($users['manager']);
$report_chapter = $runtime->chapter_service->create((object) ['work_id' => $reader_work, 'chapter_label' => 'Report checks', 'is_published' => true]);
$report_other = $runtime->chapter_service->create((object) ['work_id' => $reader_work, 'chapter_label' => 'Other report checks', 'is_published' => true]);
$report_source = $runtime->pages->for_chapter($reader_chapter['id'])[0];
$report_page = $runtime->pages->create($report_chapter['id'], (int) $report_source['attachment_id'], 720, 1100);
$report_other_page = $runtime->pages->create($report_other['id'], (int) $report_source['attachment_id'], 720, 1100);
$report_elements = new MOL\Database\ElementRepository($wpdb);
$report_now = current_time('mysql', true);
$report_element = $report_elements->insert(['page_id' => $report_page['id'], 'target_lang' => 'ar', 'element_type' => 'free_text', 'content' => 'مرجع بلاغ', 'x_unit' => 0, 'y_unit' => 0, 'w_unit' => 100000, 'h_unit' => 100000, 'rotation_mdeg' => 0, 'z_index' => 0, 'style_json' => '{}', 'version' => 1, 'created_by' => $users['translator'], 'updated_by' => $users['translator'], 'created_at' => $report_now, 'updated_at' => $report_now]);
$report_body = ['chapter_id' => $report_chapter['id'], 'report_type' => 'translation', 'message' => "<b>خطأ عربي</b>\nفي هذا الموضع"];
$post_report = static fn (array $patch = []) => $request('POST', '/reports', (object) array_replace($report_body, $patch));
$report_repo = new MOL\Database\ReportRepository($wpdb);
wp_set_current_user(0);
$expect($post_report(), 401, 'anonymous cannot report', 'ErrorResponse');
$expect($request('GET', '/reports'), 401, 'anonymous cannot list reports', 'ErrorResponse');
$expect($request('PATCH', '/reports/1', (object) ['status' => 'resolved']), 401, 'anonymous cannot moderate reports', 'ErrorResponse');
wp_set_current_user($users['member']);
$expect($request('POST', '/reports', (object) $report_body, false), 403, 'report requires nonce', 'ErrorResponse');
$expect($request('GET', '/reports'), 403, 'member cannot inspect other reports', 'ErrorResponse');
$created_report = $expect($post_report(), 201, 'ordinary reader creates chapter report without editor rights', 'ReportResponse')['data'];
$check($created_report['reporter_id'] === $users['member'] && $created_report['status'] === 'open' && $created_report['page_id'] === null && $created_report['element_id'] === null && $created_report['resolved_by'] === null, 'report actor, status and nullable references are server-derived');
$check($created_report['message'] === "خطأ عربي\nفي هذا الموضع", 'report content is sanitized plain multiline Arabic');
$expect($request('PATCH', '/reports/' . $created_report['id'], (object) ['status' => 'resolved']), 403, 'reporter cannot moderate own report', 'ErrorResponse');
foreach ([['reporter_id' => $users['manager']], ['status' => 'resolved'], ['resolved_by' => $users['manager']], ['report_type' => 'invalid'], ['message' => ''], ['message' => '<b></b>'], ['message' => str_repeat('ن', 4001)], ['chapter_id' => $reader_draft['id']], ['chapter_id' => 99999999], ['page_id' => (int) $report_other_page['id']], ['page_id' => 99999999], ['element_id' => 99999999], ['element_id' => $report_element['id'], 'page_id' => (int) $report_other_page['id']], ['element_id' => $report_element['id'], 'chapter_id' => $report_other['id']]] as $invalid) {
    $response = $expect($post_report($invalid), 400, 'reject invalid or inaccessible report target/envelope', 'ErrorResponse');
    $check($response['code'] === 'mol_invalid_params', 'report validation keeps canonical error contract');
}
$page_report = $expect($post_report(['page_id' => (int) $report_page['id'], 'report_type' => 'missing']), 201, 'page report accepted', 'ReportResponse')['data'];
$element_report = $expect($post_report(['element_id' => $report_element['id'], 'report_type' => 'placement']), 201, 'element report derives page from element', 'ReportResponse')['data'];
$check($element_report['page_id'] === (int) $report_page['id'], 'element report preserves page context for later deletion');
$expect($post_report(['page_id' => (int) $report_page['id'], 'element_id' => $report_element['id'], 'report_type' => 'style', 'message' => str_repeat('ن', 4000)]), 201, 'explicit matching parent and maximum message accepted', 'ReportResponse');
foreach (['other', 'translation'] as $kind) $expect($post_report(['report_type' => $kind]), 201, 'all report kinds accepted', 'ReportResponse');
$actor = new WP_User($users['member']); $actor->add_cap('mol_report_issue', false); wp_set_current_user(0); wp_set_current_user($users['member']);
$expect($post_report(), 403, 'individual report capability revocation applies immediately', 'ErrorResponse');
$actor->remove_cap('mol_report_issue'); wp_set_current_user(0); wp_set_current_user($users['translator']);
$expect($post_report(['chapter_id' => $reader_draft['id']]), 201, 'authenticated editor may report a readable draft', 'ReportResponse');
$expect($request('GET', '/reports'), 403, 'translator cannot list moderation queue', 'ErrorResponse');
wp_set_current_user($users['moderator']);
$expect($request('GET', '/reports', null, false), 403, 'private report list requires nonce', 'ErrorResponse');
$listing = $expect($request('GET', '/reports?chapter_id=' . $report_chapter['id'] . '&per_page=2&page=2'), 200, 'filtered report pagination', 'ReportListResponse');
$check(count($listing['data']) === 2 && $listing['meta']->total === 6 && $listing['meta']->total_pages === 3 && $listing['data'][0]['id'] > $listing['data'][1]['id'], 'stable newest-first ordering and pagination counts');
foreach (['status=bad', 'status%5B%5D=open', 'chapter_id=0', 'page=0', 'per_page=101', 'page=999999999999999999999999'] as $bad) {
    $empty = $expect($request('GET', '/reports?' . $bad), 200, 'invalid report filter matches no rows within frozen GET contract', 'ReportListResponse');
    $check($empty['data'] === [] && $empty['meta']->total === 0, 'invalid report filters cannot broaden access');
}
foreach ([new stdClass(), (object) ['status' => 'bad'], (object) ['status' => 'resolved', 'resolved_by' => $users['member']], (object) ['message' => 'replace']] as $bad) $expect($request('PATCH', '/reports/' . $created_report['id'], $bad), 400, 'moderation accepts status only', 'ErrorResponse');
$expect($request('PATCH', '/reports/99999999', (object) ['status' => 'resolved']), 404, 'missing report returns 404', 'ErrorResponse');
foreach (['in_review', 'resolved', 'open', 'rejected', 'in_review'] as $status) {
    $changed = $expect($request('PATCH', '/reports/' . $created_report['id'], (object) ['status' => $status]), 200, 'moderator changes report to ' . $status, 'ReportResponse')['data'];
    $closed = in_array($status, ['resolved', 'rejected'], true);
    $check($changed['resolved_by'] === ($closed ? $users['moderator'] : null) && ($changed['resolved_at'] !== null) === $closed, 'terminal states stamp actor/time; reopening clears resolution');
    $check($changed['message'] === $created_report['message'] && $changed['reporter_id'] === $created_report['reporter_id'], 'moderation preserves original report');
}
$resolved_report = $expect($request('PATCH', '/reports/' . $created_report['id'], (object) ['status' => 'resolved']), 200, 'resolve report', 'ReportResponse')['data'];
wp_set_current_user($users['manager']);
$again = $expect($request('PATCH', '/reports/' . $created_report['id'], (object) ['status' => 'resolved']), 200, 'repeated state write is harmless', 'ReportResponse')['data'];
$check($again === $resolved_report, 'same state cannot rewrite previous moderator attribution');
$only_resolved = $expect($request('GET', '/reports?chapter_id=' . $report_chapter['id'] . '&status=resolved'), 200, 'status and chapter filters combine', 'ReportListResponse');
$check(array_column($only_resolved['data'], 'id') === [$created_report['id']], 'status filter returns matching report only');

// An individual moderation grant is sufficient, independently of editor/content privileges.
$moderation_password = wp_generate_password(40, false);
$moderation_user = wp_insert_user(['user_login' => 'mol_report_moderator', 'user_pass' => $moderation_password, 'role' => 'mol_member']);
$check(!is_wp_error($moderation_user), 'create individual moderation fixture');
$moderation_actor = new WP_User($moderation_user); $moderation_actor->add_cap('mol_moderate_reports');
wp_set_current_user($moderation_user);
$expect($request('GET', '/reports'), 200, 'independent moderation grant lists reports', 'ReportListResponse');
$expect($request('PATCH', '/reports/' . $created_report['id'], (object) ['status' => 'open']), 200, 'independent moderation grant updates reports', 'ReportResponse');
$check(!current_user_can('mol_use_editor') && !current_user_can('mol_manage_content'), 'moderation grant does not grant editor/content access');
$moderation_actor->remove_cap('mol_moderate_reports'); wp_set_current_user(0); wp_set_current_user($moderation_user);
$expect($request('GET', '/reports'), 403, 'moderation revocation blocks read', 'ErrorResponse');
$expect($request('PATCH', '/reports/' . $created_report['id'], (object) ['status' => 'resolved']), 403, 'moderation revocation blocks write', 'ErrorResponse');
$moderation_actor->add_cap('mol_moderate_reports');

// Rate counters survive cache eviction; a rejected request inserts no row.
update_option('mol_reports_per_minute', 1);
$expect($post_report(), 201, 'first report within rate window succeeds', 'ReportResponse');
wp_cache_flush();
$before_limit = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tables->name('reports'));
$limited = $request('POST', '/reports', (object) $report_body);
$expect($limited, 429, 'report rate limit survives cache flush', 'ErrorResponse');
$check((int) ($limited->get_headers()['Retry-After'] ?? 0) > 0 && (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tables->name('reports')) === $before_limit, 'rate rejection includes Retry-After and no insert');
update_option('mol_reports_per_minute', 1000);

wp_set_current_user($users['manager']);
// A failed write must roll back cleanly and not expose database details.
$failure = static function (string $sql) use ($tables): string {
    if (str_starts_with($sql, 'INSERT INTO `' . $tables->name('reports') . '`')) throw new RuntimeException('private injected failure');
    return $sql;
};
add_filter('query', $failure);
try { $failed = $expect($post_report(), 500, 'failed report insert has generic error'); }
finally { remove_filter('query', $failure); }
$check(!str_contains(wp_json_encode($failed), 'private injected failure'), 'report failure hides implementation details');
$check((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tables->name('reports')) === $before_limit, 'failed report insert leaves no row');
$report_elements->delete($report_element['id']);
$retained = $report_repo->find($element_report['id']);
$check($retained['element_id'] === null && $retained['page_id'] === (int) $report_page['id'], 'deleting element retains report page/chapter context');

// Two actual DB connections delete a page between the report hint and parent lock.
$second_report_db = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST); $second_report_db->set_prefix($wpdb->prefix);
$second_report_runtime = new MOL\Services\ContentRuntime($second_report_db);
$report_lock_sql = $wpdb->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $tables->name('chapters'), $report_chapter['id']);
$interleaved_report = false;
$report_race = static function (string $sql) use ($report_lock_sql, &$interleaved_report, $second_report_runtime, $report_page): string {
    if (!$interleaved_report && $sql === $report_lock_sql) { $interleaved_report = true; $second_report_runtime->page_service->delete_page((int) $report_page['id']); }
    return $sql;
};
add_filter('query', $report_race);
try { $expect($post_report(['page_id' => (int) $report_page['id']]), 400, 'report rejects concurrent page deletion', 'ErrorResponse'); }
finally { remove_filter('query', $report_race); $second_report_db->close(); }
$check($interleaved_report, 'report race executes a committed second transaction');
$retained = $report_repo->find($page_report['id']);
$check($retained['page_id'] === null && $retained['element_id'] === null, 'page deletion retains report at chapter level');
$check(!(bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE page_id = %d', $tables->name('reports'), $report_page['id'])), 'concurrent creation cannot leave orphan report reference');
$runtime->page_service->delete_chapter($report_chapter['id']); $runtime->page_service->delete_chapter($report_other['id']);
$check($report_repo->find($created_report['id']) === null, 'chapter deletion removes its reports');
$wpdb->delete($tables->name('reports'), ['chapter_id' => $reader_draft['id']]);
$http_fixture += ['report_moderator_username' => 'mol_report_moderator', 'report_moderator_password' => $moderation_password];
