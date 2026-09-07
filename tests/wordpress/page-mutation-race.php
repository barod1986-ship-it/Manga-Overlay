<?php
declare(strict_types=1);

// Included only by the guarded, disposable CI content harness. Two real DB connections
// interleave transactions deterministically without sleeps, fixtures in production, or mocks.
wp_set_current_user($users['manager']);
$race_chapter = $runtime->chapter_service->create((object) ['work_id' => $work, 'chapter_label' => 'Page mutation race']);
$race_attachment = (int) $element_page['attachment_id'];
$second_db = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$second_db->set_prefix($wpdb->prefix);
$second_runtime = new MOL\Services\ContentRuntime($second_db);
$old_isolation = $wpdb->get_row("SHOW VARIABLES WHERE Variable_name IN ('transaction_isolation', 'tx_isolation')", ARRAY_A);
$original_isolation = str_replace('-', ' ', strtoupper($old_isolation['Value'] ?? ''));
if (!in_array($original_isolation, ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], true)) {
    throw new RuntimeException('Could not read the CI transaction isolation level.');
}
$wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$chapter_lock_sql = $wpdb->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $tables->name('chapters'), $race_chapter['id']);

// Pause the first connection just before its parent lock, after its snapshot read.
$interleave = static function (callable $operation, callable $concurrent) use ($chapter_lock_sql, $check): mixed {
    $ran = false;
    $hook = static function (string $sql) use ($chapter_lock_sql, &$ran, $concurrent): string {
        if (!$ran && $sql === $chapter_lock_sql) {
            $ran = true; // The second connection uses the same parent lock; never recurse.
            $concurrent();
        }
        return $sql;
    };
    add_filter('query', $hook);
    try { $result = $operation(); }
    finally { remove_filter('query', $hook); }
    $check($ran, 'second DB transaction committed between snapshot and chapter lock');
    return $result;
};

try {
    // Demonstrate the stale-read condition independently of the application guard.
    $control = $runtime->pages->create($race_chapter['id'], $race_attachment, 32, 48);
    (new MOL\Database\Transaction($wpdb))->run(static function () use ($runtime, $second_runtime, $control, $check, $wpdb, $tables): void {
        $check($runtime->pages->find((int) $control['id']) !== null, 'first connection establishes page snapshot');
        $second_runtime->page_service->delete_page((int) $control['id']);
        $check($runtime->pages->find((int) $control['id']) !== null, 'ordinary read still sees page deleted by committed second transaction');
        $current = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $tables->name('pages'), $control['id']), ARRAY_A);
        $check($current === null, 'locking read observes the committed deletion');
    });

    $create_page = $runtime->pages->create($race_chapter['id'], $race_attachment, 32, 48);
    $body = array_replace($base_element, ['page_id' => (int) $create_page['id']]);
    $response = $interleave(
        static fn () => $create($body, 'page-deleted-during-create'),
        static fn () => $second_runtime->page_service->delete_page((int) $create_page['id'])
    );
    $expect($response, 400, 'element creation rejects a page deleted after its initial read', 'ErrorResponse');
    $check($runtime->pages->find((int) $create_page['id']) === null, 'racing page deletion committed');
    $check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE page_id = %d', $tables->name('elements'), $create_page['id'])) === 0, 'race leaves no orphan element');
    $check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE chapter_id = %d', $tables->name('contributions'), $race_chapter['id'])) === 0, 'rejected race creates no contribution');
    $check((int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE scope = %s AND idempotency_key = %s', $tables->name('idempotency_keys'), 'element:create', hash('sha256', 'page-deleted-during-create'))) === 0, 'rejected race records no successful idempotency response');

    // A different page disappears while the outer deletion waits for the chapter.
    $first_race_page = $runtime->pages->create($race_chapter['id'], $race_attachment, 32, 48);
    $last_race_page = $runtime->pages->create($race_chapter['id'], $race_attachment, 32, 48);
    $response = $interleave(
        static fn () => $request('DELETE', '/pages/' . $last_race_page['id']),
        static fn () => $second_runtime->page_service->delete_page((int) $first_race_page['id'])
    );
    $expect($response, 204, 'page deletion reorders the current remaining pages after competing deletion');
    $check($runtime->pages->for_chapter($race_chapter['id']) === [], 'competing deletes leave an empty chapter without stale reorder IDs');

    $duplicate = $runtime->pages->create($race_chapter['id'], $race_attachment, 32, 48);
    $response = $interleave(
        static fn () => $request('DELETE', '/pages/' . $duplicate['id']),
        static fn () => $second_runtime->page_service->delete_page((int) $duplicate['id'])
    );
    $expect($response, 404, 'duplicate page delete observes the current missing page', 'ErrorResponse');
    $check($runtime->pages->for_chapter($race_chapter['id']) === [], 'duplicate delete does not restore a stale page');
} finally {
    $wpdb->query('SET SESSION TRANSACTION ISOLATION LEVEL ' . $original_isolation);
    $second_db->close();
    $runtime->page_service->delete_chapter($race_chapter['id']);
}
