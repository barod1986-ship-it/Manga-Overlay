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

/** @return list<array<string, mixed>> */
function molElementWriteContributionsForElement(
    ContributionRepository $contributions,
    int $chapterId,
    int $elementId
): array {
    return array_values(array_filter(
        $contributions->forChapter($chapterId),
        static fn (array $row): bool => $elementId === (int) $row['element_id']
    ));
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
$unauthenticatedRenew = molElementWriteRequest('PUT', '/mol/v1/elements/999999/lock', null, array(
    'X-MOL-Lock-Token' => str_repeat('a', 64),
));
molElementWriteAssert(401 === $unauthenticatedRenew->get_status(), 'Unauthenticated lock renew did not return 401.');
$unauthenticatedRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/999999/lock');
molElementWriteAssert(401 === $unauthenticatedRelease->get_status(), 'Unauthenticated lock release did not return 401.');

wp_set_current_user($memberId);
$forbidden = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId), array(
    'MOL-Idempotency-Key' => 't12-forbidden',
));
molElementWriteAssert(403 === $forbidden->get_status(), 'Member element create did not return 403.');
$forbiddenRenew = molElementWriteRequest('PUT', '/mol/v1/elements/999999/lock', null, array(
    'X-MOL-Lock-Token' => str_repeat('a', 64),
));
molElementWriteAssert(403 === $forbiddenRenew->get_status(), 'Member lock renew did not return 403.');
$forbiddenRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/999999/lock');
molElementWriteAssert(403 === $forbiddenRelease->get_status(), 'Member lock release did not return 403.');

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
$blockedBody = $blockedLock->get_data();
molElementWriteAssert(
    is_array($blockedBody) && '' !== (string) ($blockedBody['data']['locked_by'] ?? ''),
    'Locked response did not identify the current editor.'
);

$otherCreated = molElementWriteRequest('POST', '/mol/v1/elements', molElementCreateBody($pageId, 'عنصر مستقل للمحرر الثاني'), array(
    'MOL-Idempotency-Key' => 't13-other-element',
));
molElementWriteAssert(201 === $otherCreated->get_status(), 'Second translator could not create another page element.');
$otherCreatedBody = $otherCreated->get_data();
$otherElementId = is_array($otherCreatedBody) ? (int) ($otherCreatedBody['data']['id'] ?? 0) : 0;
molElementWriteAssert($otherElementId > 0, 'Second translator element response is malformed.');
$otherLock = molElementWriteRequest('POST', '/mol/v1/elements/' . $otherElementId . '/lock');
$otherLockBody = $otherLock->get_data();
$otherLockToken = is_array($otherLockBody) ? (string) ($otherLockBody['data']['lock_token'] ?? '') : '';
molElementWriteAssert(200 === $otherLock->get_status() && 64 === strlen($otherLockToken), 'Second translator could not lock a different element on the same page.');
$otherUpdated = molElementWriteRequest('PATCH', '/mol/v1/elements/' . $otherElementId, array('content' => 'تحرير متزامن مستقل'), array(
    'If-Match' => '"1"',
    'X-MOL-Lock-Token' => $otherLockToken,
));
molElementWriteAssert(200 === $otherUpdated->get_status(), 'Second translator could not edit a different element on the same page.');
$otherReleased = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $otherElementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $otherLockToken,
));
molElementWriteAssert(204 === $otherReleased->get_status(), 'Second translator could not release their own lock.');

wp_set_current_user($translatorId);
$missingRenewToken = molElementWriteRequest('PUT', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(409 === $missingRenewToken->get_status(), 'Lock renew without a token did not return 409.');
molElementWriteAssert('mol_lock_lost' === molElementWriteCode($missingRenewToken), 'Missing renew token code drifted.');
$wrongRenewToken = molElementWriteRequest('PUT', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => str_repeat('f', 64),
));
molElementWriteAssert(409 === $wrongRenewToken->get_status(), 'Lock renew accepted a wrong token.');
$renewed = molElementWriteRequest('PUT', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $lockToken,
));
$renewedBody = $renewed->get_data();
molElementWriteAssert(200 === $renewed->get_status(), 'Lock owner could not renew the lease.');
molElementWriteAssert($lockToken === (string) ($renewedBody['data']['lock_token'] ?? ''), 'Renew changed the lease token.');
molElementWriteAssert(
    strtotime((string) ($renewedBody['data']['expires_at'] ?? '')) >= strtotime((string) ($lockBody['data']['expires_at'] ?? '')),
    'Renew did not extend the lease expiration.'
);

$missingReleaseToken = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(409 === $missingReleaseToken->get_status(), 'Owner lock release without a token did not return 409.');
molElementWriteAssert('mol_lock_lost' === molElementWriteCode($missingReleaseToken), 'Missing release token code drifted.');

$replacementToken = bin2hex(random_bytes(32));
$lockRepository = new ElementLockRepository($wpdb);
$lockRepository->replace(
    $elementId,
    $translatorId,
    $replacementToken,
    current_time('mysql', true),
    gmdate('Y-m-d H:i:s', time() + 45)
);
$replacedRenew = molElementWriteRequest('PUT', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(409 === $replacedRenew->get_status(), 'Renew accepted a replaced token.');
$replacedRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(409 === $replacedRelease->get_status(), 'Release accepted a replaced token.');
$ownerRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $replacementToken,
));
molElementWriteAssert(204 === $ownerRelease->get_status(), 'Owner release with the current token failed.');
$releasedRenew = molElementWriteRequest('PUT', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $replacementToken,
));
molElementWriteAssert(409 === $releasedRenew->get_status(), 'Renew after release did not return 409.');

$expiringLock = molElementWriteRequest('POST', '/mol/v1/elements/' . $elementId . '/lock');
$expiringBody = $expiringLock->get_data();
$expiringToken = is_array($expiringBody) ? (string) ($expiringBody['data']['lock_token'] ?? '') : '';
$lockRepository->replace(
    $elementId,
    $translatorId,
    $expiringToken,
    gmdate('Y-m-d H:i:s', time() - 60),
    gmdate('Y-m-d H:i:s', time() - 1)
);
$expiredRenew = molElementWriteRequest('PUT', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $expiringToken,
));
molElementWriteAssert(409 === $expiredRenew->get_status(), 'Expired lease renew did not return 409.');
$expiredRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $expiringToken,
));
molElementWriteAssert(409 === $expiredRelease->get_status(), 'Expired lease release did not return 409.');

$managerCandidate = molElementWriteRequest('POST', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(200 === $managerCandidate->get_status(), 'Could not reacquire an expired lease.');
wp_set_current_user($administratorId);
$managerRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(204 === $managerRelease->get_status(), 'Manager force-release without a token failed.');
$managerIdempotentRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $elementId . '/lock');
molElementWriteAssert(204 === $managerIdempotentRelease->get_status(), 'Manager force-release was not idempotent.');

wp_set_current_user($translatorId);
$finalLock = molElementWriteRequest('POST', '/mol/v1/elements/' . $elementId . '/lock');
$finalLockBody = $finalLock->get_data();
$lockToken = is_array($finalLockBody) ? (string) ($finalLockBody['data']['lock_token'] ?? '') : '';
molElementWriteAssert(200 === $finalLock->get_status() && 64 === strlen($lockToken), 'Could not acquire the final write lease.');

$missingElementAcquire = molElementWriteRequest('POST', '/mol/v1/elements/999999/lock');
molElementWriteAssert(404 === $missingElementAcquire->get_status(), 'Acquire for a missing element did not return 404.');
$missingElementRenew = molElementWriteRequest('PUT', '/mol/v1/elements/999999/lock', null, array(
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(404 === $missingElementRenew->get_status(), 'Renew for a missing element did not return 404.');
$missingElementRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/999999/lock', null, array(
    'X-MOL-Lock-Token' => $lockToken,
));
molElementWriteAssert(404 === $missingElementRelease->get_status(), 'Release for a missing element did not return 404.');

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
$automaticContribution = molElementWriteContributionsForElement($contributions, $chapterId, $elementId);
molElementWriteAssert(1 === count($automaticContribution), 'Element create/PATCH did not record one contribution.');
molElementWriteAssert($translatorId === $automaticContribution[0]['user_id'], 'Element contribution used the wrong user.');
molElementWriteAssert(true === $automaticContribution[0]['created_element'], 'Creator attribution was not recorded.');
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
molElementWriteAssert(
    array() === molElementWriteContributionsForElement($contributions, $chapterId, $elementId),
    'Element contribution was orphaned.'
);
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

$t14WorkId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T14 Contributions',
), true);
molElementWriteAssert(is_int($t14WorkId) && $t14WorkId > 0, 'Could not create the T14 work.');
$t14ChapterId = $chapters->insert(array(
    'work_id' => $t14WorkId,
    'chapter_label' => '14',
    'slug' => 't14-contributions',
    'translation_status' => 'in_progress',
    'is_published' => true,
    'published_at' => current_time('mysql', true),
    'created_by' => $administratorId,
));
$t14PageId = $pages->insert($t14ChapterId, 0, 900_014, 800, 1_200);

wp_set_current_user($translatorId);
$t14CreateBody = molElementCreateBody($t14PageId, 'عنصر مساهمة T14');
$t14Created = molElementWriteRequest('POST', '/mol/v1/elements', $t14CreateBody, array(
    'MOL-Idempotency-Key' => 't14-contribution-element',
));
$t14CreatedBody = $t14Created->get_data();
$t14ElementId = is_array($t14CreatedBody) ? (int) ($t14CreatedBody['data']['id'] ?? 0) : 0;
molElementWriteAssert(201 === $t14Created->get_status() && $t14ElementId > 0, 'T14 element create failed.');

$t14InitialRows = molElementWriteContributionsForElement($contributions, $t14ChapterId, $t14ElementId);
molElementWriteAssert(1 === count($t14InitialRows), 'T14 create did not insert exactly one contribution.');
molElementWriteAssert(true === $t14InitialRows[0]['created_element'], 'T14 create lost creator attribution.');
$t14InitialContribution = $t14InitialRows[0];

$t14Replay = molElementWriteRequest('POST', '/mol/v1/elements', $t14CreateBody, array(
    'MOL-Idempotency-Key' => 't14-contribution-element',
));
molElementWriteAssert(201 === $t14Replay->get_status(), 'T14 idempotent create replay failed.');
molElementWriteAssert(
    $t14InitialRows === molElementWriteContributionsForElement($contributions, $t14ChapterId, $t14ElementId),
    'Idempotent create replay changed or duplicated the contribution.'
);

$t14Lock = molElementWriteRequest('POST', '/mol/v1/elements/' . $t14ElementId . '/lock');
$t14LockBody = $t14Lock->get_data();
$t14LockToken = is_array($t14LockBody) ? (string) ($t14LockBody['data']['lock_token'] ?? '') : '';
molElementWriteAssert(200 === $t14Lock->get_status() && 64 === strlen($t14LockToken), 'T14 creator lock failed.');

$t14Version = 1;
for ($autosave = 1; $autosave <= 10; ++$autosave) {
    $t14Updated = molElementWriteRequest(
        'PATCH',
        '/mol/v1/elements/' . $t14ElementId,
        array('content' => sprintf('T14 autosave %d', $autosave)),
        array(
            'If-Match' => sprintf('"%d"', $t14Version),
            'X-MOL-Lock-Token' => $t14LockToken,
        )
    );
    $t14UpdatedBody = $t14Updated->get_data();
    ++$t14Version;
    molElementWriteAssert(200 === $t14Updated->get_status(), sprintf('T14 autosave %d failed.', $autosave));
    molElementWriteAssert(
        is_array($t14UpdatedBody) && $t14Version === (int) ($t14UpdatedBody['data']['version'] ?? 0),
        sprintf('T14 autosave %d returned the wrong version.', $autosave)
    );
}

$t14CreatorRows = molElementWriteContributionsForElement($contributions, $t14ChapterId, $t14ElementId);
molElementWriteAssert(1 === count($t14CreatorRows), 'Ten autosaves duplicated the creator contribution.');
molElementWriteAssert(
    $t14InitialContribution['first_contributed_at'] === $t14CreatorRows[0]['first_contributed_at'],
    'Autosave changed first_contributed_at.'
);
molElementWriteAssert(
    $t14CreatorRows[0]['last_contributed_at'] >= $t14CreatorRows[0]['first_contributed_at'],
    'Autosave moved last_contributed_at before the first contribution.'
);
molElementWriteAssert(true === $t14CreatorRows[0]['created_element'], 'Autosave cleared creator attribution.');

$t14CreatorRelease = molElementWriteRequest('DELETE', '/mol/v1/elements/' . $t14ElementId . '/lock', null, array(
    'X-MOL-Lock-Token' => $t14LockToken,
));
molElementWriteAssert(204 === $t14CreatorRelease->get_status(), 'T14 creator lock release failed.');

wp_set_current_user($otherTranslatorId);
$t14CollaboratorLock = molElementWriteRequest('POST', '/mol/v1/elements/' . $t14ElementId . '/lock');
$t14CollaboratorLockBody = $t14CollaboratorLock->get_data();
$t14CollaboratorToken = is_array($t14CollaboratorLockBody)
    ? (string) ($t14CollaboratorLockBody['data']['lock_token'] ?? '')
    : '';
molElementWriteAssert(
    200 === $t14CollaboratorLock->get_status() && 64 === strlen($t14CollaboratorToken),
    'T14 second contributor lock failed.'
);
$t14CollaboratorUpdate = molElementWriteRequest(
    'PATCH',
    '/mol/v1/elements/' . $t14ElementId,
    array('content' => 'تحرير المساهم الثاني'),
    array(
        'If-Match' => sprintf('"%d"', $t14Version),
        'X-MOL-Lock-Token' => $t14CollaboratorToken,
    )
);
molElementWriteAssert(200 === $t14CollaboratorUpdate->get_status(), 'T14 second contributor PATCH failed.');
$t14CollaboratorRelease = molElementWriteRequest(
    'DELETE',
    '/mol/v1/elements/' . $t14ElementId . '/lock',
    null,
    array('X-MOL-Lock-Token' => $t14CollaboratorToken)
);
molElementWriteAssert(204 === $t14CollaboratorRelease->get_status(), 'T14 second contributor release failed.');

$t14Rows = molElementWriteContributionsForElement($contributions, $t14ChapterId, $t14ElementId);
molElementWriteAssert(2 === count($t14Rows), 'A second editor did not create a second unique contribution.');
$t14RowsByUser = array();
foreach ($t14Rows as $row) {
    $t14RowsByUser[(int) $row['user_id']] = $row;
}
molElementWriteAssert(true === ($t14RowsByUser[$translatorId]['created_element'] ?? null), 'Creator row lost its flag.');
molElementWriteAssert(false === ($t14RowsByUser[$otherTranslatorId]['created_element'] ?? null), 'Editor row was marked as creator.');

$t14ContributorTotals = $contributions->contributorsForChapter($t14ChapterId);
molElementWriteAssert(2 === count($t14ContributorTotals), 'T14 chapter contributor aggregate count drifted.');
foreach ($t14ContributorTotals as $total) {
    molElementWriteAssert(1 === $total['element_count'], 'A chapter view counted saves instead of unique elements.');
}

wp_set_current_user(0);
$t14ContributorsResponse = molElementWriteRequest('GET', '/mol/v1/chapters/' . $t14ChapterId . '/contributors');
$t14ContributorsBody = $t14ContributorsResponse->get_data();
molElementWriteAssert(200 === $t14ContributorsResponse->get_status(), 'T14 contributor REST view failed.');
molElementWriteAssert(
    is_array($t14ContributorsBody) && 2 === (int) ($t14ContributorsBody['meta']['count'] ?? 0),
    'T14 contributor REST view omitted a contributor.'
);
molElementWriteAssert(function_exists('mol_theme_chapter_contributors_data'), 'Theme contributor data bridge is missing.');
$t14ThemeContributors = mol_theme_chapter_contributors_data($t14ChapterId);
molElementWriteAssert(200 === $t14ThemeContributors['status'], 'Theme contributor view failed.');
molElementWriteAssert(2 === count($t14ThemeContributors['data']), 'Theme contributor view did not expose both users.');

wp_set_current_user($administratorId);
echo "Manga Overlay element-write integration passed.\n";
