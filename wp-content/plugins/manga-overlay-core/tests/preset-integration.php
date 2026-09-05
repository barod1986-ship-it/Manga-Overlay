<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Content\WorkContent;
use MOL\Repositories\StylePresetRepository;
use MOL\Services\ElementStyleResolver;

function molPresetAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $body */
function molPresetRequest(string $method, string $path, array $body = array()): WP_REST_Response
{
    $route = strtok($path, '?');
    $request = new WP_REST_Request($method, is_string($route) ? $route : $path);
    $query = parse_url($path, PHP_URL_QUERY);
    if (is_string($query) && '' !== $query) {
        parse_str($query, $queryParams);
        $request->set_query_params($queryParams);
    }
    if (array() !== $body || in_array($method, array('POST', 'PATCH'), true)) {
        $request->set_header('Content-Type', 'application/json');
        $encoded = wp_json_encode($body);
        molPresetAssert(is_string($encoded), 'Could not encode preset request.');
        $request->set_body($encoded);
    }

    return rest_do_request($request);
}

function molPresetUser(string $username, string $role): int
{
    $userId = wp_create_user($username, wp_generate_password(32), $username . '@example.invalid');
    molPresetAssert(! is_wp_error($userId), sprintf('Could not create %s.', $username));
    $user = get_user_by('id', $userId);
    molPresetAssert($user instanceof WP_User, sprintf('Could not load %s.', $username));
    $user->set_role($role);

    return (int) $userId;
}

/** @return array<string, mixed> */
function molPresetData(WP_REST_Response $response): array
{
    $body = $response->get_data();

    return is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : array();
}

global $wpdb;
molPresetAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
$administratorId = get_current_user_id();
molPresetAssert($administratorId > 0, 'Run preset integration as an administrator.');

$routes = rest_get_server()->get_routes();
molPresetAssert(isset($routes['/mol/v1/presets']), 'Preset collection route is missing.');
molPresetAssert(isset($routes['/mol/v1/presets/(?P<id>\d+)']), 'Preset item route is missing.');

$workId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T15 Preset Work',
), true);
molPresetAssert(is_int($workId) && $workId > 0, 'Could not create preset work.');
$translatorId = molPresetUser('mol_t15_translator', 'mol_translator');
$otherTranslatorId = molPresetUser('mol_t15_other', 'mol_translator');
$moderatorId = molPresetUser('mol_t15_moderator', 'mol_moderator');

wp_set_current_user(0);
molPresetAssert(401 === molPresetRequest('GET', '/mol/v1/presets?work_id=' . $workId)->get_status(), 'Anonymous preset list did not return 401.');

wp_set_current_user($translatorId);
$personalOne = molPresetRequest('POST', '/mol/v1/presets', array(
    'scope' => 'personal',
    'work_id' => null,
    'name' => 'Personal One',
    'element_type' => 'bubble',
    'style' => array('color' => '#112233'),
    'is_default' => true,
));
molPresetAssert(201 === $personalOne->get_status(), 'Translator could not create a personal preset.');
$personalOneId = (int) molPresetData($personalOne)['id'];
$personalTwo = molPresetRequest('POST', '/mol/v1/presets', array(
    'scope' => 'personal',
    'name' => 'Personal Two',
    'element_type' => 'bubble',
    'style' => array('color' => '#223344'),
    'is_default' => true,
));
molPresetAssert(201 === $personalTwo->get_status(), 'Second personal preset create failed.');
$personalTwoId = (int) molPresetData($personalTwo)['id'];
$repository = new StylePresetRepository($wpdb);
molPresetAssert(false === $repository->find($personalOneId)['is_default'], 'Old personal default was not cleared atomically.');
molPresetAssert(true === $repository->find($personalTwoId)['is_default'], 'New personal default was not activated.');

$forbiddenWork = molPresetRequest('POST', '/mol/v1/presets', array(
    'scope' => 'work',
    'work_id' => $workId,
    'name' => 'Forbidden Work',
    'element_type' => 'bubble',
    'style' => array('color' => '#334455'),
));
molPresetAssert(403 === $forbiddenWork->get_status(), 'Translator managed a work preset.');

wp_set_current_user($moderatorId);
$workPreset = molPresetRequest('POST', '/mol/v1/presets', array(
    'scope' => 'work',
    'work_id' => $workId,
    'name' => 'Work Default',
    'element_type' => 'bubble',
    'style' => array('color' => '#445566'),
    'is_default' => true,
));
molPresetAssert(201 === $workPreset->get_status(), 'Moderator could not create a work preset.');
$workPresetId = (int) molPresetData($workPreset)['id'];
$forbiddenGlobal = molPresetRequest('POST', '/mol/v1/presets', array(
    'scope' => 'global',
    'name' => 'Forbidden Global',
    'element_type' => 'bubble',
    'style' => array('color' => '#556677'),
));
molPresetAssert(403 === $forbiddenGlobal->get_status(), 'Moderator managed a global preset.');

wp_set_current_user($administratorId);
$globalPreset = molPresetRequest('POST', '/mol/v1/presets', array(
    'scope' => 'global',
    'name' => 'Global Default',
    'element_type' => 'bubble',
    'style' => array('color' => '#667788'),
    'is_default' => true,
));
molPresetAssert(201 === $globalPreset->get_status(), 'Manager could not create a global preset.');
$globalPresetId = (int) molPresetData($globalPreset)['id'];

wp_set_current_user($translatorId);
$available = molPresetRequest('GET', '/mol/v1/presets?work_id=' . $workId . '&type=bubble');
molPresetAssert(200 === $available->get_status(), 'Translator could not list available presets.');
$availableData = molPresetData($available);
$availableIds = array_map(static fn (array $preset): int => (int) $preset['id'], $availableData);
molPresetAssert(
    array() === array_diff(array($personalOneId, $personalTwoId, $workPresetId, $globalPresetId), $availableIds),
    'Preset list did not contain personal, work, and global scopes.'
);
$personalPosition = array_search($personalOneId, $availableIds, true);
$workPosition = array_search($workPresetId, $availableIds, true);
$globalPosition = array_search($globalPresetId, $availableIds, true);
molPresetAssert(
    is_int($personalPosition) && is_int($workPosition) && is_int($globalPosition)
        && $personalPosition < $workPosition && $workPosition < $globalPosition,
    'Preset scope ordering drifted.'
);

$resolver = new ElementStyleResolver($repository);
$chapter = array('work_id' => $workId);
$resolved = $resolver->resolve($translatorId, $chapter, 'bubble', null, array());
molPresetAssert('#223344' === $resolved['color'], 'Personal default did not win resolution precedence.');
molPresetAssert(200 === molPresetRequest('PATCH', '/mol/v1/presets/' . $personalTwoId, array('is_default' => false))->get_status(), 'Personal default could not be disabled.');
$resolved = $resolver->resolve($translatorId, $chapter, 'bubble', null, array());
molPresetAssert('#445566' === $resolved['color'], 'Work default did not win after personal default was disabled.');
wp_set_current_user($moderatorId);
molPresetAssert(200 === molPresetRequest('PATCH', '/mol/v1/presets/' . $workPresetId, array('is_default' => false))->get_status(), 'Work default could not be disabled.');
wp_set_current_user($translatorId);
$resolved = $resolver->resolve($translatorId, $chapter, 'bubble', null, array());
molPresetAssert('#667788' === $resolved['color'], 'Global default did not win after higher scopes were disabled.');
molPresetAssert(200 === molPresetRequest('PATCH', '/mol/v1/presets/' . $personalOneId, array('is_default' => true))->get_status(), 'Personal default could not be reassigned.');
molPresetAssert(false === $repository->find($personalTwoId)['is_default'], 'Reassigned personal default left another default active.');

$invalidStyle = molPresetRequest('PATCH', '/mol/v1/presets/' . $personalOneId, array('style' => array('shape' => 'impact')));
molPresetAssert(400 === $invalidStyle->get_status(), 'Preset patch accepted a style invalid for its element type.');

wp_set_current_user($otherTranslatorId);
molPresetAssert(403 === molPresetRequest('PATCH', '/mol/v1/presets/' . $personalOneId, array('name' => 'Stolen'))->get_status(), 'Another translator changed a personal preset.');
try {
    $resolver->resolve($otherTranslatorId, $chapter, 'bubble', $personalOneId, array());
    throw new RuntimeException('Another translator explicitly applied a private personal preset.');
} catch (MOL\REST\ApiException $error) {
    molPresetAssert(403 === $error->status(), 'Private preset access returned the wrong status.');
}

$denyWork = static fn (bool $allowed, int $userId, WP_Post $work): bool => $work->ID === $workId && $userId === $otherTranslatorId
    ? false
    : $allowed;
add_filter('mol_user_can_edit_work', $denyWork, 10, 3);
molPresetAssert(403 === molPresetRequest('GET', '/mol/v1/presets?work_id=' . $workId)->get_status(), 'Work policy did not restrict preset visibility.');
remove_filter('mol_user_can_edit_work', $denyWork, 10);

wp_set_current_user($administratorId);
molPresetAssert(204 === molPresetRequest('DELETE', '/mol/v1/presets/' . $globalPresetId)->get_status(), 'Global preset delete failed.');
molPresetAssert(null === $repository->find($globalPresetId), 'Deleted global preset still exists.');
molPresetAssert(null !== $repository->find($workPresetId), 'Work preset was deleted unexpectedly.');

wp_set_current_user($administratorId);
echo "Manga Overlay T-15 preset integration passed.\n";
