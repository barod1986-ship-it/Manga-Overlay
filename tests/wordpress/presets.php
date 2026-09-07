<?php
declare(strict_types=1);

// Included from the guarded disposable content harness, before fixture export.
$make_preset = static fn (array $changes = []) => $request('POST', '/presets', (object) array_replace(['scope' => 'personal', 'name' => 'نمط عربي', 'element_type' => 'bubble', 'style' => (object) ['color' => '#223344']], $changes));
$list_presets = static fn (string $query = '') => $expect($request('GET', '/presets' . $query), 200, 'list only available editor presets', 'PresetListResponse')['data'];
wp_set_current_user(0);
$expect($request('GET', '/presets'), 401, 'anonymous cannot list presets', 'ErrorResponse');
$expect($make_preset(), 401, 'anonymous cannot create preset', 'ErrorResponse');
wp_set_current_user($users['member']);
$expect($make_preset(), 403, 'member cannot create personal preset', 'ErrorResponse');
wp_set_current_user($users['translator']);
$expect($request('GET', '/presets', null, false), 403, 'preset list needs authenticated nonce', 'ErrorResponse');
$mine = $expect($make_preset(['is_default' => true]), 201, 'translator creates own default preset', 'PresetResponse')['data'];
$check($mine['owner_user_id'] === $users['translator'] && $mine['work_id'] === null && $mine['is_default'] === true, 'preset ownership is derived server-side and DTO is typed');
$expect($make_preset(['scope' => 'global']), 403, 'translator cannot create global preset', 'ErrorResponse');
$expect($make_preset(['scope' => 'work', 'work_id' => $work]), 403, 'translator cannot create work preset', 'ErrorResponse');
foreach ([['owner_user_id' => $users['manager']], ['content' => 'not style'], ['x_unit' => 1], ['work_id' => $work], ['style' => (object) ['burst' => (object) ['points' => 8]]], ['style' => (object) ['fontId' => 'https://example.invalid/font']], ['name' => '<b></b>']] as $bad) {
    $expect($make_preset($bad), 400, 'reject preset scope, structural or type-specific violation', 'ErrorResponse');
}
$expect($request('PATCH', '/presets/' . $mine['id'], new stdClass()), 400, 'empty preset patch rejected', 'ErrorResponse');
$expect($request('PATCH', '/presets/' . $mine['id'], (object) ['scope' => 'global']), 400, 'preset scope is immutable', 'ErrorResponse');
$expect($request('PATCH', '/presets/' . $mine['id'], (object) ['style' => (object) ['scaleX' => 1.5]]), 400, 'preset patch validates stored type', 'ErrorResponse');
$next = $expect($make_preset(['name' => 'الافتراضي الثاني', 'is_default' => true]), 201, 'replace default in same personal scope', 'PresetResponse')['data'];
$check(count(array_filter($list_presets('?type=bubble'), static fn ($p) => $p['scope'] === 'personal' && $p['is_default'])) === 1, 'one personal default remains');
$updated = $expect($request('PATCH', '/presets/' . $mine['id'], (object) ['name' => '<b>محدث</b>', 'is_default' => true, 'style' => (object) ['tail' => (object) ['enabled' => true]]]), 200, 'patch preset name, style and default', 'PresetResponse')['data'];
$check($updated['name'] === 'محدث' && $updated['style']->color === '#223344' && $updated['style']->tail->enabled, 'preset patch preserves sibling style and sanitizes name');
$service = new MOL\Services\PresetService($wpdb);
$failure = static function (string $sql) use ($tables): string {
    if (str_starts_with($sql, 'INSERT INTO `' . $tables->name('style_presets') . '`')) { throw MOL\Domain\Fault::invalid('Injected CI failure after default clear.'); }
    return $sql;
};
add_filter('query', $failure);
try { $fault(static fn () => $service->create((object) ['scope' => 'personal', 'name' => 'rollback', 'element_type' => 'bubble', 'style' => new stdClass(), 'is_default' => true]), 'mol_invalid_params'); }
finally { remove_filter('query', $failure); }
$check(array_values(array_filter($list_presets('?type=bubble'), static fn ($p) => $p['scope'] === 'personal' && $p['is_default']))[0]['id'] === $mine['id'], 'rollback restores previous default');
wp_set_current_user($users['manager']);
$check(!in_array($mine['id'], array_column($list_presets(), 'id'), true), 'manager cannot list another user personal preset');
$expect($request('PATCH', '/presets/' . $mine['id'], (object) ['name' => 'stolen']), 403, 'manager cannot mutate another personal preset', 'ErrorResponse');
$expect($request('DELETE', '/presets/' . $mine['id']), 403, 'manager cannot delete another personal preset', 'ErrorResponse');
$expect($make_preset(['scope' => 'work']), 400, 'work preset requires work ID', 'ErrorResponse');
$expect($make_preset(['scope' => 'work', 'work_id' => 999999999]), 400, 'work preset rejects missing parent', 'ErrorResponse');
$shared = $expect($make_preset(['scope' => 'work', 'work_id' => $work, 'is_default' => true]), 201, 'manager publishes work default', 'PresetResponse')['data'];
$global_preset = $expect($make_preset(['scope' => 'global', 'is_default' => true]), 201, 'manager publishes global default', 'PresetResponse')['data'];
wp_set_current_user($users['translator']);
$ids = array_column($list_presets('?work_id=' . $work . '&type=bubble'), 'id');
$check(in_array($shared['id'], $ids, true) && in_array($global_preset['id'], $ids, true) && in_array($mine['id'], $ids, true), 'editor sees personal, current-work and global styles');
$check(!in_array($shared['id'], array_column($list_presets('?work_id=' . $other_work), 'id'), true), 'another work context excludes work preset');
$check(!in_array($shared['id'], array_column($list_presets(), 'id'), true), 'work presets require explicit context');
$check($list_presets('?type=invalid') === [] && $list_presets('?work_id%5B%5D=1') === [], 'invalid list filters match no rows within frozen response contract');
$expect($request('DELETE', '/presets/' . $shared['id']), 403, 'editor cannot delete work preset', 'ErrorResponse');
$expect($request('PATCH', '/presets/' . $global_preset['id'], (object) ['is_default' => true]), 403, 'editor cannot update global preset', 'ErrorResponse');
$user = new WP_User($users['translator']); $user->add_cap('mol_manage_work_presets'); wp_set_current_user(0); wp_set_current_user($users['translator']);
$expect($request('PATCH', '/presets/' . $shared['id'], (object) ['name' => 'مخول فرديًا']), 200, 'individual work capability allows update', 'PresetResponse');
$user->remove_cap('mol_manage_work_presets'); wp_set_current_user(0); wp_set_current_user($users['translator']);
$expect($request('PATCH', '/presets/' . $shared['id'], (object) ['name' => 'revoked']), 403, 'individual work capability revocation applies next request', 'ErrorResponse');
$user->remove_cap('mol_use_editor'); wp_set_current_user(0); wp_set_current_user($users['translator']);
$expect($request('DELETE', '/presets/' . $mine['id']), 403, 'editor revocation blocks own preset deletion', 'ErrorResponse');
$user->add_cap('mol_use_editor'); wp_set_current_user(0); wp_set_current_user($users['translator']);
foreach ([$mine['id'], $next['id']] as $preset_id) { $expect($request('DELETE', '/presets/' . $preset_id), 204, 'owner deletes personal preset'); }
$expect($request('DELETE', '/presets/' . $mine['id']), 404, 'repeat deletion reports missing preset', 'ErrorResponse');
$expect($request('PATCH', '/presets/' . $mine['id'], (object) ['name' => 'missing']), 404, 'patch missing preset reports 404', 'ErrorResponse');
wp_set_current_user($users['manager']);
foreach ([$shared['id'], $global_preset['id']] as $preset_id) { $expect($request('DELETE', '/presets/' . $preset_id), 204, 'manager deletes shared preset'); }
$orphan_work = wp_insert_post(['post_type' => 'mol_work', 'post_status' => 'publish', 'post_title' => 'Preset deletion cleanup']);
$orphan_preset = $expect($make_preset(['scope' => 'work', 'work_id' => $orphan_work]), 201, 'create work-only preset for cleanup', 'PresetResponse')['data'];
wp_delete_post($orphan_work, true);
$check((new MOL\Database\PresetRepository($wpdb))->find($orphan_preset['id']) === null, 'permanent work deletion cleans work presets under mutation lock');
