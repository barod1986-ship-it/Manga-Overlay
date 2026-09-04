<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Content\WorkContent;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementLockRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReportRepository;

function molElementWriteAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed>|null $body @param array<string, string> $headers */
function molElementWriteRequest(string $method, string $path, ?array $body = null, array $headers = array()): WP_REST_Response
{
    $request = new WP_REST_Request($method, $path);
    foreach ($headers as $name => $value) {
        $request->set_header($name, $value);
    }
    if (null !== $body) {
        $request->set_header('Content-Type', 'application/json');
        $encoded = wp_json_encode($body);
        molElementWriteAssert(is_string($encoded), 'Could not encode an element REST payload.');
        $request->set_body($encoded);
    }

    return rest_do_request($request);
}

function molElementWriteCode(WP_REST_Response $response): string
{
    $body = $response->get_data();

    return is_array($body) && is_string($body['code'] ?? null) ? $body['code'] : '';
}

function molElementWriteUser(string $username, string $role): int
{
    $userId = wp_create_user($username, wp_generate_password(32), $username . '@example.invalid');
    molElementWriteAssert(! is_wp_error($userId), sprintf('Could not create %s.', $username));
    $user = get_user_by('id', $userId);
    molElementWriteAssert($user instanceof WP_User, sprintf('Could not load %s.', $username));
    $user->set_role($role);

    return (int) $userId;
}

/** @return array<string, mixed> */
function molElementCreateBody(int $pageId, string $content = 'نص T12 آمن'): array
{
    return array(
        'page_id' => $pageId,
        'target_lang' => 'ar',
        'element_type' => 'bubble',
        'x_unit' => 100_000,
        'y_unit' => 120_000,
        'w_unit' => 300_000,
        'h_unit' => 180_000,
        'rotation_mdeg' => 0,
        'z_index' => 2,
        'content' => $content,
        'style' => array('tail' => array('enabled' => false)),
    );
}

global $wpdb;
molElementWriteAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
$administratorId = get_current_user_id();
molElementWriteAssert($administratorId > 0, 'Run element-write integration as an administrator.');

$routes = rest_get_server()->get_routes();
foreach (array(
    '/mol/v1/elements',
    '/mol/v1/elements/(?P<id>\d+)',
    '/mol/v1/elements/(?P<id>\d+)/lock',
) as $route) {
    molElementWriteAssert(isset($routes[$route]), sprintf('REST route %s is missing.', $route));
}

$workId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T12 Element Writes',
), true);
molElementWriteAssert(is_int($workId) && $workId > 0, 'Could not create the T12 work.');
$chapters = new ChapterRepository($wpdb);
$chapterId = $chapters->insert(array(
    'work_id' => $workId,
    'chapter_label' => '12',
    'slug' => 't12-element-writes',
    'translation_status' => 'in_progress',
    'is_published' => true,
    'created_by' => $administratorId,
));
$pages = new PageRepository($wpdb);
$pageId = $pages->insert($chapterId, 0, 900_012, 800, 1_200);
$translatorId = molElementWriteUser('mol_t12_translator', 'mol_translator');
$otherTranslatorId = molElementWriteUser('mol_t12_other', 'mol_translator');
$memberId = molElementWriteUser('mol_t12_member', 'mol_member');

wp_set_current_user(0);
$unauthenticated = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId), array(
    'MOL-Idempotency-Key' => 't12-unauthenticated',
));
molElementWriteAssert(401 === $unauthenticated->get_status(), 'Unauthenticated element create did not return 401.');

wp_set_current_user($memberId);
$forbidden = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId), array(
    'MOL-Idempotency-Key' => 't12-forbidden',
));
molElementWriteAssert(403 === $forbidden->get_status(), 'Member element create did not return 403.');

wp_set_current_user($translatorId);
$missingKey = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId));
molElementWriteAssert(400 === $missingKey->get_status(), 'Element create without idempotency key did not return 400.');
$invalidBody = molElementCreateBody($pageId);
$invalidBody['raw_html'] = '<b>unsupported</b>';
$invalidCreate = molElementWriteRequest('POST', '/mol/v1/elements', $invalidBody, array(
    'MOL-Idempotency-Key' => 't12-invalid',
));
molElementWriteAssert(400 === $invalidCreate->get_status(), 'Unknown element property did not return 400.');

$unsafeLiteral = '</script><img src=x onerror=alert(1)>نص محفوظ كنص';
$created = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId, $unsafeLiteral), array(
    'MOL-Idempotency-Key' => 't12-create-one',
));
molElementWriteAssert(201 === $created->get_status(), 'Translator could not create an element.');
$createdBody = $created->get_data();
molElementWriteAssert(is_array($createdBody) && isset($createdBody['data']['id']), 'Element create response is malformed.');
$elementId = (int) $createdBody['data']['id'];
molElementWriteAssert(1 === (int) $createdBody['data']['version'], 'Created element version is not 1.');
molElementWriteAssert('"1"' === ($created->get_headers()['ETag'] ?? ''), 'Create ETag is not the quoted version.');
molElementWriteAssert($unsafeLiteral === $createdBody['data']['content'], 'Plaintext element content changed.');
molElementWriteAssert('cairo' === $createdBody['data']['style']['fontId'], 'Resolved Base Style is incomplete.');
molElementWriteAssert(false === $createdBody['data']['style']['tail']['enabled'], 'Style override was not resolved.');
molElementWriteAssert(80_000 === $createdBody['data']['style']['tail']['lengthUnit'], 'Nested Base Style was discarded.');
molElementWriteAssert(
    'private, no-store, max-age=0' === ($created->get_headers()['Cache-Control'] ?? ''),
    'Element write response can be cached.'
);

$replayed = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId, $unsafeLiteral), array(
    'MOL-Idempotency-Key' => 't12-create-one',
));
$replayedBody = $replayed->get_data();
molElementWriteAssert(201 === $replayed->get_status(), 'Idempotent element replay did not return 201.');
molElementWriteAssert($elementId === (int) $replayedBody['data']['id'], 'Idempotent replay duplicated the element.');
$mismatch = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId, 'payload changed'), array(
    'MOL-Idempotency-Key' => 't12-create-one',
));
molElementWriteAssert(409 === $mismatch->get_status(), 'Element idempotency mismatch did not return 409.');
molElementWriteAssert('mol_idempotency_mismatch' === molElementWriteCode($mismatch), 'Element mismatch code drifted.');

$missingPrecondition = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $elementId, array('content' => 'تعديل'));
molElementWriteAssert(428 === $missingPrecondition->get_status(), 'PATCH without If-Match did not return 428.');
molElementWriteAssert('mol_precondition_required' === molElementWriteCode($missingPrecondition), '428 code drifted.');
$withoutLock = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $elementId, array('content' => 'تعديل'), array(
    'If-Match' => '"1"',
));
molElementWriteAssert(423 === $withoutLock->get_status(), 'PATCH without a valid lock did not return 423.');

$lock = molElementWriteRequest('POST', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(200 === $lock->get_status(), 'Translator could not acquire an element lock.');
$lockBody = $lock->get_data();
$lockToken = is_array($lockBody) ? (string) ($lockBody['data']['lock_token'] ?? '') : '';
molElementWriteAssert(64 === strlen($lockToken), 'Lock token is not a 256-bit hex value.');
molElementWriteAssert(is_string($lockBody['data']['expires_at'] ?? null), 'Lock response omitted expires_at.');

wp_set_current_user($otherTranslatorId);
$blockedLock = molElementWriteRequest('POST', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(423 === $blockedLock->get_status(), 'A second translator replaced an active lock.');

wp_set_current_user($translatorId);
$wrongLock = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $elementId, array('content' => 'تعديل'), array(
    'If-Match' => '"1"',
    'X-MOL-Lock-Token' => str_repeat('f', 64),
));
molElementWriteAssert(423 === $wrongLock->get_status(), 'PATCH accepted a wrong lock token.');
$stale = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $elementId, array('content' => 'تعديل'), array(
    'If-Match' => '"9"',
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(412 === $stale->get_status(), 'Stale If-Match did not return 412.');
molElementWriteAssert('mol_version_conflict' === molElementWriteCode($stale), 'Version conflict code drifted.');

$updated = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $elementId, array(
    'content' => "سطر أول\nسطر ثانٍ",
    'x_unit' => 130_000,
    'element_type' => 'bubble',
    'style' => array('tail' => array('angleMdeg' => 40_000)),
), array(
    'If-Match' => '"1"',
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(200 === $updated->get_status(), 'Conditional element PATCH failed.');
$updatedBody = $updated->get_data();
molElementWriteAssert(2 === (int) $updatedBody['data']['version'], 'PATCH did not increment version exactly once.');
molElementWriteAssert('"2"' === ($updated->get_headers()['ETag'] ?? ''), 'PATCH ETag did not track version.');
molElementWriteAssert(130_000 === (int) $updatedBody['data']['x_unit'], 'PATCH geometry did not persist.');
molElementWriteAssert(40_000 === (int) $updatedBody['data']['style']['tail']['angleMdeg'], 'Nested style patch failed.');
molElementWriteAssert(false === $updatedBody['data']['style']['tail']['enabled'], 'Nested style patch lost stored siblings.');

$invalidGeometry = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $elementId, array('w_unit' => 950_000), array(
    'If-Match' => '"2"',
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(400 === $invalidGeometry->get_status(), 'Cross-field invalid geometry did not return 400.');
molElementWriteAssert(2 === (new ElementRepository($wpdb))->find($elementId)['version'], 'Invalid PATCH incremented version.');

$contributions = new ContributionRepository($wpdb);
$contributions->upsert($elementId, $translatorId, $workId, $chapterId, true);
$reports = new ReportRepository($wpdb);
$reportId = $reports->insert(array(
    'chapter_id' => $chapterId,
    'page_id' => $pageId,
    'element_id' => $elementId,
    'reporter_id' => $memberId,
    'report_type' => 'translation',
    'message' => 'T12 deletion cascade',
));
$staleDelete = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId, null, array(
    'If-Match' => '"1"',
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(412 === $staleDelete->get_status(), 'Stale conditional DELETE did not return 412.');
$deleted = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId, null, array(
    'If-Match' => '"2"',
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(204 === $deleted->get_status(), 'Conditional element DELETE failed.');
molElementWriteAssert(null === (new ElementRepository($wpdb))->find($elementId), 'Deleted element still exists.');
molElementWriteAssert(null === (new ElementLockRepository($wpdb))->findForElement($elementId), 'Element lock was orphaned.');
molElementWriteAssert(array() === $contributions->forChapter($chapterId), 'Element contribution was orphaned.');
molElementWriteAssert(null === $reports->find($reportId), 'Element report was orphaned.');

$rateUserId = molElementWriteUser('mol_t12_rate', 'mol_translator');
wp_set_current_user($rateUserId);
$oneWrite = static fn (int $limit): int => 1;
add_filter('mol_element_write_rate_limit', $oneWrite);
$rateFirst = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId, 'first'), array(
    'MOL-Idempotency-Key' => 't12-rate-first',
));
$rateSecond = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId, 'second'), array(
    'MOL-Idempotency-Key' => 't12-rate-second',
));
remove_filter('mol_element_write_rate_limit', $oneWrite);
molElementWriteAssert(201 === $rateFirst->get_status(), 'First element write was rate-limited.');
molElementWriteAssert(429 === $rateSecond->get_status(), 'Element write limiter did not return 429.');
molElementWriteAssert(isset($rateSecond->get_headers()['Retry-After']), 'Element rate limit omitted Retry-After.');

wp_set_current_user($administratorId);
echo "Manga Overlay element-write integration passed.\n";
