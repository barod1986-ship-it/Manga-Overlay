<?php
/** Execute only through WP-CLI in the disposable CI database. */
declare(strict_types=1);

use MOL\Content\WorkType;
use MOL\Database\ChapterRepository;
use MOL\Database\Migrator;
use MOL\Database\Tables;
use MOL\Database\Transaction;
use MOL\Domain\Geometry;
use MOL\Security\Roles;

if (!defined('WP_CLI') || !WP_CLI || getenv('MOL_TEST_ENV') !== '1' || wp_get_environment_type() !== 'local') {
	throw new RuntimeException('Integration tests require the disposable local CI environment.');
}

global $wpdb;
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	++$checks;
	WP_CLI::log('PASS ' . $message);
};
$throws = static function (callable $operation, string $class, string $message) use ($check): void {
	try {
		$operation();
	} catch (Throwable $error) {
		$check($error instanceof $class, $message);
		return;
	}
	$check(false, $message);
};

$tables = new Tables($wpdb);
$check($wpdb->prefix === 'qa_', 'non-default WordPress prefix');
$check(get_option('mol_db_version') === Migrator::VERSION, 'activation records schema version');
$check(get_option('mol_roles_version') === Roles::VERSION, 'activation records role version');
foreach (Tables::NAMES as $name) {
	$metadata = $wpdb->get_row($wpdb->prepare('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $tables->name($name)), ARRAY_A);
	$check($metadata !== null && strtoupper($metadata['ENGINE']) === 'INNODB' && str_starts_with($metadata['TABLE_COLLATION'], 'utf8mb4'), $name . ' uses InnoDB/utf8mb4');
}
$check((new Migrator($wpdb))->migrate() === [], 'dbDelta migration is idempotent');
$throws(static fn () => $tables->name('chapters; DROP TABLE'), InvalidArgumentException::class, 'table allowlist rejects injection');

$users = [];
foreach (['member', 'translator', 'moderator', 'manager'] as $role) {
	$id = wp_insert_user(['user_login' => 'ci_' . $role, 'user_pass' => wp_generate_password(32), 'role' => 'mol_' . $role]);
	$check(!is_wp_error($id), 'create test ' . $role);
	$users[$role] = new WP_User($id);
}
$check(!$users['member']->has_cap('mol_use_editor'), 'member cannot edit');
$check($users['translator']->has_cap('mol_use_editor') && !$users['translator']->has_cap('mol_manage_content'), 'translator editing is separate from content management');
$check($users['moderator']->has_cap('mol_review_translations') && !$users['moderator']->has_cap('mol_manage_content'), 'review capability does not grant content management');
$check(!$users['manager']->has_cap('manage_options'), 'content manager does not gain site settings');
foreach (Roles::CAPABILITIES as $cap) {
	$check($users['manager']->has_cap($cap), 'manager receives ' . $cap);
}
$users['member']->add_cap('mol_upload_content');
$check($users['member']->has_cap('mol_upload_content') && !$users['member']->has_cap('mol_manage_content'), 'upload can be granted independently');
$users['translator']->add_cap('mol_manage_content');
$check($users['translator']->has_cap('mol_manage_content'), 'management can be granted independently');
$users['translator']->remove_cap('mol_manage_content');
Roles::install();
$check(!(new WP_User($users['translator']->ID))->has_cap('mol_manage_content'), 'normal bootstrap preserves revoked individual grants');

$post_type = get_post_type_object('mol_work');
$check($post_type && $post_type->show_in_rest && post_type_supports('mol_work', 'custom-fields'), 'work CPT supports registered REST meta');
$check($post_type->has_archive === 'library' && $post_type->rewrite['slug'] === 'series', 'library and series permalinks are registered');
$check(count(get_terms(['taxonomy' => 'mol_work_type', 'hide_empty' => false])) === 6, 'exactly six canonical work types');
WorkType::seed_types();
$check(count(get_terms(['taxonomy' => 'mol_work_type', 'hide_empty' => false])) === 6, 'work type seeding is idempotent');
$check(is_wp_error(wp_insert_term('invalid-type', 'mol_work_type')), 'unknown work type rejected');

$create_work = static function (): WP_REST_Response {
	$request = new WP_REST_Request('POST', '/wp/v2/mol_work');
	$request->set_body_params(['title' => 'عمل الاختبار', 'status' => 'publish', 'meta' => ['_mol_alt_titles' => ['اسم بديل'], '_mol_default_reader_mode' => 'paged', '_mol_reading_direction' => 'rtl']]);
	return rest_do_request($request);
};
wp_set_current_user($users['member']->ID);
$check($create_work()->get_status() === 403, 'member cannot create works through core REST');
wp_set_current_user($users['moderator']->ID);
$check($create_work()->get_status() === 403, 'review-only moderator cannot create works');
wp_set_current_user($users['manager']->ID);
$response = $create_work();
$check($response->get_status() === 201, 'content manager creates work through core REST');
$work_id = (int) $response->get_data()['id'];
$check(get_post_meta($work_id, '_mol_alt_titles', true) === ['اسم بديل'], 'alternative titles persist through registered meta');
$check(get_post_meta($work_id, '_mol_default_reader_mode', true) === 'paged', 'reader mode persists through registered meta');
$check(current_user_can('edit_post', $work_id) && current_user_can('mol_manage_content'), 'core meta capability mapping preserves direct domain capability');
wp_set_current_user($users['translator']->ID);
$request = new WP_REST_Request('POST', '/wp/v2/mol_work/' . $work_id);
$request->set_body_params(['meta' => ['_mol_reading_direction' => 'ltr']]);
$check(rest_do_request($request)->get_status() === 403, 'translator cannot change work metadata');
wp_set_current_user(0);
$check(rest_do_request(new WP_REST_Request('GET', '/wp/v2/mol_work/' . $work_id))->get_status() === 200, 'published work remains public');

$now = current_time('mysql', true);
$insert_chapter = static function (string $slug) use ($wpdb, $tables, $work_id, $users, $now): int {
	$result = $wpdb->insert($tables->name('chapters'), ['work_id' => $work_id, 'chapter_label' => '1.5', 'sort_order' => '1.5000', 'slug' => $slug, 'created_by' => $users['manager']->ID, 'created_at' => $now, 'updated_at' => $now]);
	if ($result !== 1) {
		throw new RuntimeException('Could not insert CI chapter.');
	}
	return (int) $wpdb->insert_id;
};
$chapter_id = $insert_chapter('chapter-1-5');
$repository = new ChapterRepository($wpdb);
$chapter = $repository->find($chapter_id);
$check($chapter !== null && is_float($chapter['sort_order']) && $chapter['sort_order'] === 1.5, 'DECIMAL is serialized as a DTO number');
$check($chapter['is_published'] === false && $chapter['published_at'] === null, 'chapter booleans and nullable dates are normalized');
$check(str_ends_with($chapter['created_at'], 'Z'), 'database dates serialize as UTC');
$check($repository->find(99999999) === null, 'missing chapter is represented explicitly');
$check(wp_delete_post($work_id, true) === false, 'core work deletion cannot orphan chapters');
$check(wp_trash_post($work_id) === false, 'core work trash cannot hide a parent with chapters');

$chapter_table = $tables->name('chapters');
$backup_table = $chapter_table . '_ci_unavailable';
$wpdb->query($wpdb->prepare('RENAME TABLE %i TO %i', $chapter_table, $backup_table));
$previous_errors = $wpdb->suppress_errors();
try {
	$throws(static fn () => $repository->find($chapter_id), RuntimeException::class, 'database failure is distinct from a missing chapter');
	$check(wp_delete_post($work_id, true) === false, 'database failure cannot bypass parent deletion guard');
} finally {
	$wpdb->suppress_errors($previous_errors);
	$wpdb->query($wpdb->prepare('RENAME TABLE %i TO %i', $backup_table, $chapter_table));
}

$transaction = new Transaction($wpdb);
$throws(static function () use ($transaction, $insert_chapter): void {
	$transaction->run(static function () use ($insert_chapter): void {
		$insert_chapter('rollback-sentinel');
		throw new RuntimeException('Simulated service failure');
	});
}, RuntimeException::class, 'service exception rolls back');
$check(!(bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE slug = %s', $tables->name('chapters'), 'rollback-sentinel')), 'rolled-back data is absent');
$transaction->run(static fn () => $insert_chapter('commit-sentinel'));
$check((bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM %i WHERE slug = %s', $tables->name('chapters'), 'commit-sentinel')), 'transaction commits valid service data');
$throws(static fn () => $transaction->run(static fn () => (new Transaction($wpdb))->run(static fn () => true)), LogicException::class, 'nested transaction on same connection rejected');

$valid = ['x_unit' => 100000, 'y_unit' => 200000, 'w_unit' => 300000, 'h_unit' => 100000];
$check(Geometry::validate($valid)['rotation_mdeg'] === 0, 'geometry defaults are canonical');
foreach (['w_unit' => 0, 'x_unit' => 900000, 'rotation_mdeg' => 360001, 'z_index' => 10001, 'unknown' => 1] as $key => $value) {
	$throws(static fn () => Geometry::validate(array_merge($valid, [$key => $value])), InvalidArgumentException::class, 'invalid geometry rejected: ' . $key);
}
$throws(static fn () => Geometry::validate(array_merge($valid, ['x_unit' => '100000'])), InvalidArgumentException::class, 'numeric strings are not persisted as geometry integers');

// Failed engine verification must not advance the migration marker.
delete_option('mol_db_version');
$wpdb->query($wpdb->prepare('ALTER TABLE %i ENGINE=MyISAM', $tables->name('reports')));
$throws(static fn () => (new Migrator($wpdb))->migrate(), RuntimeException::class, 'nontransactional table engine rejected');
$check(get_option('mol_db_version') === false, 'failed migration does not mark schema as current');
$wpdb->query($wpdb->prepare('ALTER TABLE %i ENGINE=InnoDB', $tables->name('reports')));
(new Migrator($wpdb))->migrate();
$check($repository->find($chapter_id) !== null, 'repeat migration preserves existing rows');

deactivate_plugins('manga-overlay-core/manga-overlay-core.php');
$check($repository->find($chapter_id) !== null && get_option('mol_db_version') === Migrator::VERSION, 'deactivation preserves data and schema version');
WP_CLI::success($checks . ' WordPress integration checks passed.');
