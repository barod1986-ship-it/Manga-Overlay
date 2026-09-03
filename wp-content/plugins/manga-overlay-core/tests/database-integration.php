<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Activation\Activator;
use MOL\Activation\RoleManager;
use MOL\Database\Migrator;
use MOL\Database\Schema;
use MOL\Database\TableNames;
use MOL\Database\TransactionManager;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementLockRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\IdempotencyKeyRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReadingProgressRepository;
use MOL\Repositories\ReportRepository;
use MOL\Repositories\StylePresetRepository;
use MOL\Support\Versions;

function molIntegrationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @return list<string> */
function molIntegrationIndexNames(wpdb $database, string $table): array
{
    $rows = $database->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
    molIntegrationAssert(is_array($rows), sprintf('Could not inspect indexes for %s.', $table));

    return array_values(array_unique(array_map(
        static fn (array $row): string => (string) $row['Key_name'],
        $rows
    )));
}

global $wpdb;
molIntegrationAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
molIntegrationAssert(Versions::DATABASE === (string) get_option(Versions::DATABASE_OPTION), 'DB version was not activated.');
molIntegrationAssert(Versions::ROLES === (string) get_option(Versions::ROLES_OPTION), 'Role version was not activated.');

// Exercise the real activation callback a second time before inspecting state.
Activator::activate();
foreach (RoleManager::managedRoleSlugs() as $roleSlug) {
    $role = get_role($roleSlug);
    molIntegrationAssert($role instanceof WP_Role, sprintf('WordPress role %s is missing.', $roleSlug));
    foreach (RoleManager::canonicalCapabilities() as $capability) {
        $expected = in_array($capability, RoleManager::capabilitiesForRole($roleSlug), true);
        molIntegrationAssert(
            $expected === $role->has_cap($capability),
            sprintf('%s has an incorrect value for %s.', $roleSlug, $capability)
        );
    }
}
$administrator = get_role('administrator');
molIntegrationAssert($administrator instanceof WP_Role, 'The administrator role is missing.');
foreach (RoleManager::canonicalCapabilities() as $capability) {
    molIntegrationAssert($administrator->has_cap($capability), sprintf('Administrator is missing %s.', $capability));
}

$optionTable = $wpdb->options;
foreach (array(Versions::DATABASE_OPTION, Versions::ROLES_OPTION) as $optionName) {
    $autoload = $wpdb->get_var($wpdb->prepare(
        "SELECT autoload FROM `{$optionTable}` WHERE option_name = %s",
        $optionName
    ));
    molIntegrationAssert(
        is_string($autoload) && ! in_array($autoload, wp_autoload_values_to_autoload(), true),
        sprintf('%s must not autoload.', $optionName)
    );
}

$tables = new TableNames($wpdb->prefix);
molIntegrationAssert(9 === count($tables->all()), 'Expected exactly nine domain tables.');
foreach ($tables->all() as $suffix => $table) {
    $engine = $wpdb->get_var($wpdb->prepare(
        'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $table
    ));
    molIntegrationAssert(is_string($engine) && 0 === strcasecmp('InnoDB', $engine), sprintf('%s is not InnoDB.', $table));

    $foreignKeyCount = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = %s',
        $table
    ));
    molIntegrationAssert(0 === $foreignKeyCount, sprintf('%s unexpectedly contains a foreign key.', $table));

    $columnRows = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
    molIntegrationAssert(is_array($columnRows), sprintf('Could not inspect columns for %s.', $table));
    molIntegrationAssert(
        Schema::requiredColumns()[$suffix] === array_column($columnRows, 'Field'),
        sprintf('%s columns do not match the canonical schema.', $table)
    );

    $actualIndexes = molIntegrationIndexNames($wpdb, $table);
    foreach (Schema::requiredIndexes()[$suffix] as $requiredIndex) {
        molIntegrationAssert(in_array($requiredIndex, $actualIndexes, true), sprintf(
            '%s is missing index %s.',
            $table,
            $requiredIndex
        ));
    }

    $indexRows = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);
    molIntegrationAssert(is_array($indexRows), sprintf('Could not inspect index uniqueness for %s.', $table));
    foreach ($indexRows as $indexRow) {
        $indexName = (string) $indexRow['Key_name'];
        if ('PRIMARY' === $indexName || str_starts_with($indexName, 'uq_')) {
            molIntegrationAssert(0 === (int) $indexRow['Non_unique'], sprintf(
                '%s index %s must be unique.',
                $table,
                $indexName
            ));
        }
    }
}

$sortOrderColumn = $wpdb->get_row(
    "SHOW COLUMNS FROM `{$tables->chapters}` LIKE 'sort_order'",
    ARRAY_A
);
molIntegrationAssert(
    is_array($sortOrderColumn) && 'decimal(14,4)' === strtolower((string) $sortOrderColumn['Type']),
    'sort_order is not decimal(14,4).'
);

// A second real dbDelta pass must not damage or duplicate the schema.
(new Migrator())->migrate();
foreach ($tables->all() as $table) {
    molIntegrationAssert($table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)), sprintf(
        'Idempotent migration lost %s.',
        $table
    ));
}

update_option('timezone_string', 'Asia/Muscat');
$userId = get_current_user_id();
molIntegrationAssert($userId > 0, 'Run the integration test with a WordPress user.');
$workId = wp_insert_post(array(
    'post_title' => 'Migration integration work',
    'post_status' => 'publish',
    'post_type' => 'post',
), true);
molIntegrationAssert(is_int($workId) && $workId > 0, 'Could not create the WordPress work fixture.');
$attachmentId = wp_insert_attachment(array(
    'post_title' => 'Migration page fixture',
    'post_status' => 'inherit',
    'post_mime_type' => 'image/png',
), '', 0, true);
molIntegrationAssert(is_int($attachmentId) && $attachmentId > 0, 'Could not create the attachment fixture.');

$chapters = new ChapterRepository($wpdb);
$chapterId = $chapters->insert(array(
    'work_id' => $workId,
    'chapter_label' => '10.5',
    'sort_order' => 10.5,
    'title' => 'الفصل التجريبي',
    'slug' => 'integration-chapter',
    'reader_mode_override' => 'paged',
    'direction_override' => 'rtl',
    'created_by' => $userId,
));
$chapter = $chapters->find($chapterId);
molIntegrationAssert(null !== $chapter, 'Chapter repository did not return the inserted row.');
molIntegrationAssert(is_float($chapter['sort_order']) && 10.5 === $chapter['sort_order'], 'sort_order was not normalized to float.');
molIntegrationAssert(false === $chapter['is_published'], 'Chapter boolean normalization failed.');
$chapterTimestamp = strtotime((string) $chapter['created_at'] . ' UTC');
molIntegrationAssert(false !== $chapterTimestamp && abs(time() - $chapterTimestamp) < 10, 'Chapter timestamp was not written in UTC.');

$pages = new PageRepository($wpdb);
$pageId = $pages->insert($chapterId, 0, $attachmentId, 1600, 2400);
molIntegrationAssert(0 === $pages->find($pageId)['page_index'], 'Page repository did not normalize page_index.');

$elements = new ElementRepository($wpdb);
$style = array(
    'shape' => 'impact',
    'fontId' => 'sfx-display-1',
    'color' => '#112233',
    'burst' => array('points' => 16, 'depth' => 0.7),
    'scaleX' => 1.2,
    'scaleY' => 0.8,
);
$elementId = $elements->insert(array(
    'page_id' => $pageId,
    'target_lang' => 'ar',
    'element_type' => 'sfx',
    'x_unit' => 100000,
    'y_unit' => 120000,
    'w_unit' => 300000,
    'h_unit' => 200000,
    'rotation_mdeg' => -15000,
    'z_index' => 3,
    'content' => 'دوي!',
    'style' => $style,
    'created_by' => $userId,
));
$element = $elements->find($elementId);
molIntegrationAssert(null !== $element && $style === $element['style'], 'Element style JSON did not round-trip.');
molIntegrationAssert(-15000 === $element['rotation_mdeg'], 'Element integer normalization failed.');

$now = gmdate('Y-m-d H:i:s');
$later = gmdate('Y-m-d H:i:s', time() + 120);
$locks = new ElementLockRepository($wpdb);
$locks->insert($elementId, $userId, str_repeat('a', 64), $now, $later);
molIntegrationAssert($userId === $locks->findForElement($elementId)['user_id'], 'Element lock repository failed.');

$contributions = new ContributionRepository($wpdb);
$contributions->upsert($elementId, $userId, $workId, $chapterId, true, $now);
$contributions->upsert($elementId, $userId, $workId, $chapterId, false, $later);
$chapterContributions = $contributions->forChapter($chapterId);
molIntegrationAssert(1 === count($chapterContributions), 'Contribution UPSERT created a duplicate row.');
molIntegrationAssert(true === $chapterContributions[0]['created_element'], 'Contribution UPSERT lost creator attribution.');
molIntegrationAssert($later === $chapterContributions[0]['last_contributed_at'], 'Contribution UPSERT did not refresh its timestamp.');

$reports = new ReportRepository($wpdb);
$reportId = $reports->insert(array(
    'chapter_id' => $chapterId,
    'page_id' => $pageId,
    'element_id' => $elementId,
    'reporter_id' => $userId,
    'report_type' => 'placement',
    'message' => 'Integration report',
));
molIntegrationAssert($elementId === $reports->find($reportId)['element_id'], 'Report repository failed.');

$progress = new ReadingProgressRepository($wpdb);
$progress->upsert($userId, $chapterId, 0, 200000, 'webtoon', $now);
$progress->upsert($userId, $chapterId, 0, 600000, 'paged', $later);
$savedProgress = $progress->find($userId, $chapterId);
molIntegrationAssert(null !== $savedProgress && 600000 === $savedProgress['progress_unit'], 'Reading progress UPSERT failed.');
molIntegrationAssert('paged' === $savedProgress['reader_mode'], 'Reading mode was not updated.');

$presets = new StylePresetRepository($wpdb);
$presets->insert(array(
    'scope' => 'global',
    'name' => 'Global Bubble',
    'element_type' => 'bubble',
    'style' => array('shape' => 'ellipse'),
    'is_default' => true,
    'created_by' => $userId,
));
$presets->insert(array(
    'scope' => 'work',
    'work_id' => $workId,
    'name' => 'Work Bubble',
    'element_type' => 'bubble',
    'style' => array('shape' => 'cloud'),
    'is_default' => true,
    'created_by' => $userId,
));
$personalPresetId = $presets->insert(array(
    'scope' => 'personal',
    'owner_user_id' => $userId,
    'name' => 'Personal Bubble',
    'element_type' => 'bubble',
    'style' => array('shape' => 'rounded_rect'),
    'is_default' => true,
    'created_by' => $userId,
));
$presetCandidates = $presets->defaultCandidates($userId, $workId, 'bubble');
molIntegrationAssert(
    array('personal', 'work', 'global') === array_column($presetCandidates, 'scope'),
    'Preset candidates do not follow personal/work/global precedence.'
);
molIntegrationAssert('rounded_rect' === $presets->find($personalPresetId)['style']['shape'], 'Preset JSON did not round-trip.');

$idempotency = new IdempotencyKeyRepository($wpdb);
$idempotencyId = $idempotency->insert(array(
    'user_id' => $userId,
    'scope' => 'page-upload:' . $chapterId,
    'idempotency_key' => 'integration-key',
    'request_hash' => hash('sha256', 'fixture'),
    'resource_type' => 'page',
    'resource_id' => $pageId,
    'response_code' => 201,
    'response' => array('id' => $pageId),
    'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
));
$idempotencyRecord = $idempotency->find($userId, 'page-upload:' . $chapterId, 'integration-key');
molIntegrationAssert(null !== $idempotencyRecord && $idempotencyId === $idempotencyRecord['id'], 'Idempotency lookup failed.');
molIntegrationAssert(array('id' => $pageId) === $idempotencyRecord['response'], 'Idempotency response JSON failed.');

$reportCountBefore = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables->reports}");
$transactions = new TransactionManager($wpdb);
try {
    $transactions->run(static function () use ($reports, $chapterId, $userId): void {
        $reports->insert(array(
            'chapter_id' => $chapterId,
            'reporter_id' => $userId,
            'report_type' => 'other',
            'message' => 'This row must roll back',
        ));
        throw new RuntimeException('Intentional rollback');
    });
    throw new RuntimeException('Transaction callback did not throw.');
} catch (RuntimeException $error) {
    molIntegrationAssert('Intentional rollback' === $error->getMessage(), 'Transaction replaced the original error.');
}
$reportCountAfter = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables->reports}");
molIntegrationAssert($reportCountBefore === $reportCountAfter, 'Transaction rollback left a report row behind.');
molIntegrationAssert(! $transactions->isActive(), 'Transaction remained active after rollback.');

$databaseVersion = (string) $wpdb->get_var('SELECT VERSION()');
echo sprintf("Manga Overlay database integration passed on %s.\n", $databaseVersion);
