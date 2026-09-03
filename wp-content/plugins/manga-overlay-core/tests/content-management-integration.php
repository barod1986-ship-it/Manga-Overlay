<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Content\WorkContent;
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
use MOL\Services\MediaService;

function molManagementIntegrationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $body */
function molManagementJsonRequest(string $method, string $path, array $body): WP_REST_Response
{
    $request = new WP_REST_Request($method, $path);
    $request->set_header('Content-Type', 'application/json');
    $encoded = wp_json_encode($body);
    molManagementIntegrationAssert(is_string($encoded), 'Could not encode a REST test payload.');
    $request->set_body($encoded);

    return rest_do_request($request);
}

/** @param array<string, mixed> $file */
function molManagementUploadRequest(int $chapterId, string $key, array $file): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/mol/v1/chapters/' . $chapterId . '/pages');
    $request->set_header('MOL-Idempotency-Key', $key);
    $request->set_file_params(array('image' => $file));

    return rest_do_request($request);
}

/** @return array<string, mixed> */
function molManagementImageFile(string $bytes, string $name, string $mime = 'image/png'): array
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'mol-page-');
    molManagementIntegrationAssert(is_string($temporaryPath), 'Could not create a temporary upload file.');
    molManagementIntegrationAssert(false !== file_put_contents($temporaryPath, $bytes), 'Could not write an upload fixture.');

    return array(
        'name' => $name,
        'type' => $mime,
        'tmp_name' => $temporaryPath,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($bytes),
    );
}

function molManagementErrorCode(WP_REST_Response $response): string
{
    $data = $response->get_data();

    return is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
}

function molManagementCreateUser(string $username, string $role): int
{
    $userId = wp_create_user($username, wp_generate_password(32), $username . '@example.invalid');
    molManagementIntegrationAssert(! is_wp_error($userId), sprintf('Could not create %s.', $username));
    $user = get_user_by('id', $userId);
    molManagementIntegrationAssert($user instanceof WP_User, sprintf('Could not load %s.', $username));
    $user->set_role($role);

    return (int) $userId;
}

global $wpdb;
molManagementIntegrationAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
$administratorId = get_current_user_id();
molManagementIntegrationAssert($administratorId > 0, 'Run the management integration test as an administrator.');

$routes = rest_get_server()->get_routes();
foreach (array(
    '/mol/v1/chapters',
    '/mol/v1/chapters/(?P<id>\d+)',
    '/mol/v1/chapters/(?P<id>\d+)/review',
    '/mol/v1/chapters/(?P<id>\d+)/pages',
    '/mol/v1/chapters/(?P<id>\d+)/pages/reorder',
    '/mol/v1/pages/(?P<id>\d+)',
) as $route) {
    molManagementIntegrationAssert(isset($routes[$route]), sprintf('REST route %s is missing.', $route));
}

$workId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T-06 Integration Work',
), true);
molManagementIntegrationAssert(is_int($workId) && $workId > 0, 'Could not create the T-06 work fixture.');

wp_set_current_user(0);
$unauthenticatedCreate = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => '1',
));
molManagementIntegrationAssert(401 === $unauthenticatedCreate->get_status(), 'Unauthenticated chapter create did not return 401.');
molManagementIntegrationAssert('mol_not_authenticated' === molManagementErrorCode($unauthenticatedCreate), 'Unauthenticated error code drifted.');

$memberId = molManagementCreateUser('mol_t06_member', 'mol_member');
wp_set_current_user($memberId);
$forbiddenCreate = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => '1',
));
molManagementIntegrationAssert(403 === $forbiddenCreate->get_status(), 'Member chapter create did not return 403.');
molManagementIntegrationAssert('mol_forbidden' === molManagementErrorCode($forbiddenCreate), 'Forbidden error code drifted.');

wp_set_current_user($administratorId);
$created = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => '1',
    'sort_order' => 1,
    'title' => 'Pilot Chapter',
    'translation_status' => 'untranslated',
    'reader_mode_override' => 'paged',
    'direction_override' => 'rtl',
    'is_published' => false,
));
molManagementIntegrationAssert(201 === $created->get_status(), 'Administrator could not create a chapter.');
$createdBody = $created->get_data();
molManagementIntegrationAssert(is_array($createdBody) && isset($createdBody['data']['id']), 'Chapter create response is malformed.');
$chapterId = (int) $createdBody['data']['id'];
molManagementIntegrationAssert('pilot-chapter' === $createdBody['data']['slug'], 'Chapter slug was not generated from title.');
molManagementIntegrationAssert(false === $createdBody['data']['is_published'], 'Chapter publication flag changed.');

$collision = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => '2',
    'title' => 'Pilot Chapter',
));
molManagementIntegrationAssert(201 === $collision->get_status(), 'Chapter slug collision was not resolved.');
$collisionBody = $collision->get_data();
molManagementIntegrationAssert('pilot-chapter-2' === $collisionBody['data']['slug'], 'Chapter slug suffix drifted.');
$collisionChapterId = (int) $collisionBody['data']['id'];

$invalidCreate = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => '3',
    'slug' => 'client-controlled',
));
molManagementIntegrationAssert(400 === $invalidCreate->get_status(), 'Unknown chapter property did not return 400.');
molManagementIntegrationAssert('mol_invalid_params' === molManagementErrorCode($invalidCreate), 'Invalid parameter error code drifted.');

$invalidPatch = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId, array());
molManagementIntegrationAssert(400 === $invalidPatch->get_status(), 'Empty chapter patch did not return 400.');

$published = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId, array(
    'is_published' => true,
    'translation_status' => 'in_progress',
));
molManagementIntegrationAssert(200 === $published->get_status(), 'Chapter patch failed.');
$publishedBody = $published->get_data();
molManagementIntegrationAssert(true === $publishedBody['data']['is_published'], 'Chapter was not published.');
molManagementIntegrationAssert(is_string($publishedBody['data']['published_at']), 'Published chapter has no publication timestamp.');

$moderatorId = molManagementCreateUser('mol_t06_moderator', 'mol_moderator');
wp_set_current_user($moderatorId);
$reviewed = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId . '/review', array(
    'translation_status' => 'needs_review',
));
molManagementIntegrationAssert(200 === $reviewed->get_status(), 'Moderator could not review a chapter.');
$reviewedBody = $reviewed->get_data();
molManagementIntegrationAssert('needs_review' === $reviewedBody['data']['translation_status'], 'Review status did not persist.');
$completedReview = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId . '/review', array(
    'translation_status' => 'completed',
));
molManagementIntegrationAssert(200 === $completedReview->get_status(), 'Moderator could not complete a reviewed chapter.');
$invalidReview = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId . '/review', array(
    'translation_status' => 'in_progress',
));
molManagementIntegrationAssert(400 === $invalidReview->get_status(), 'Review route accepted a general status.');
$moderatorCreate = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => 'forbidden',
));
molManagementIntegrationAssert(403 === $moderatorCreate->get_status(), 'Review capability granted chapter creation.');
$moderatorDelete = rest_do_request(new WP_REST_Request('DELETE', '/mol/v1/chapters/' . $collisionChapterId));
molManagementIntegrationAssert(403 === $moderatorDelete->get_status(), 'Review capability granted chapter deletion.');

add_role('mol_t06_upload_only', 'MOL T06 Upload Only', array('read' => true, 'mol_upload_content' => true));
add_role('mol_t06_manage_only', 'MOL T06 Manage Only', array('read' => true, 'mol_manage_content' => true));
$uploaderId = molManagementCreateUser('mol_t06_uploader', 'mol_t06_upload_only');
$managerOnlyId = molManagementCreateUser('mol_t06_manager_only', 'mol_t06_manage_only');

$imagePath = tempnam(sys_get_temp_dir(), 'mol-source-');
molManagementIntegrationAssert(is_string($imagePath), 'Could not create the source image fixture.');
$imageResource = imagecreatetruecolor(8, 8);
molManagementIntegrationAssert(false !== $imageResource, 'GD could not create the source image fixture.');
$background = imagecolorallocate($imageResource, 32, 96, 160);
imagefill($imageResource, 0, 0, $background);
molManagementIntegrationAssert(imagepng($imageResource, $imagePath), 'GD could not encode the PNG fixture.');
imagedestroy($imageResource);
$imageBytes = file_get_contents($imagePath);
molManagementIntegrationAssert(is_string($imageBytes) && '' !== $imageBytes, 'Could not read the PNG fixture.');
unlink($imagePath);
$jpegPath = tempnam(sys_get_temp_dir(), 'mol-source-jpeg-');
molManagementIntegrationAssert(is_string($jpegPath), 'Could not create the JPEG fixture.');
$jpegResource = imagecreatetruecolor(8, 8);
molManagementIntegrationAssert(false !== $jpegResource, 'GD could not create the JPEG image.');
$jpegBackground = imagecolorallocate($jpegResource, 180, 80, 32);
imagefill($jpegResource, 0, 0, $jpegBackground);
molManagementIntegrationAssert(imagejpeg($jpegResource, $jpegPath, 90), 'GD could not encode the JPEG fixture.');
imagedestroy($jpegResource);
$jpegBytes = file_get_contents($jpegPath);
molManagementIntegrationAssert(is_string($jpegBytes) && '' !== $jpegBytes, 'Could not read the JPEG fixture.');
unlink($jpegPath);

wp_set_current_user($managerOnlyId);
$managerUpload = molManagementUploadRequest(
    $chapterId,
    'manager-cannot-upload',
    molManagementImageFile($imageBytes, 'manager.png')
);
molManagementIntegrationAssert(403 === $managerUpload->get_status(), 'mol_manage_content unexpectedly granted upload.');
$managerPatch = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId, array('sort_order' => 1.5));
molManagementIntegrationAssert(200 === $managerPatch->get_status(), 'Manage-only user could not update a chapter.');

wp_set_current_user($uploaderId);
$uploaderPatch = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId, array('sort_order' => 2));
molManagementIntegrationAssert(403 === $uploaderPatch->get_status(), 'mol_upload_content unexpectedly granted chapter management.');
$missingImageRequest = new WP_REST_Request('POST', '/mol/v1/chapters/' . $chapterId . '/pages');
$missingImageRequest->set_header('MOL-Idempotency-Key', 'missing-image');
$missingImage = rest_do_request($missingImageRequest);
molManagementIntegrationAssert(400 === $missingImage->get_status(), 'Upload without an image did not return 400.');
molManagementIntegrationAssert('mol_invalid_params' === molManagementErrorCode($missingImage), 'Missing-image error code drifted.');
$uploaded = molManagementUploadRequest(
    $chapterId,
    'upload-page-one',
    molManagementImageFile($imageBytes, 'page-01.png')
);
molManagementIntegrationAssert(201 === $uploaded->get_status(), 'Upload-only user could not upload a valid page.');
$uploadedBody = $uploaded->get_data();
molManagementIntegrationAssert(is_array($uploadedBody) && isset($uploadedBody['data']['id']), 'Page response is malformed.');
$uploadedPageId = (int) $uploadedBody['data']['id'];
$uploadedAttachmentId = (int) $uploadedBody['data']['image']['attachment_id'];
molManagementIntegrationAssert('attachment' === get_post_type($uploadedAttachmentId), 'Valid upload did not create an attachment.');
molManagementIntegrationAssert(8 === $uploadedBody['data']['natural_width'], 'Natural image width was not persisted.');

$pages = new PageRepository($wpdb);
$pageCount = count($pages->forChapter($chapterId));
$replayed = molManagementUploadRequest(
    $chapterId,
    'upload-page-one',
    molManagementImageFile($imageBytes, 'page-01.png')
);
molManagementIntegrationAssert(201 === $replayed->get_status(), 'Idempotent upload replay did not return the original response.');
$replayedBody = $replayed->get_data();
molManagementIntegrationAssert($uploadedPageId === (int) $replayedBody['data']['id'], 'Idempotent upload created a different page.');
molManagementIntegrationAssert($pageCount === count($pages->forChapter($chapterId)), 'Idempotent replay duplicated a page.');

$mismatch = molManagementUploadRequest(
    $chapterId,
    'upload-page-one',
    molManagementImageFile($imageBytes, 'renamed-page.png')
);
molManagementIntegrationAssert(409 === $mismatch->get_status(), 'Idempotency payload mismatch did not return 409.');
molManagementIntegrationAssert('mol_idempotency_mismatch' === molManagementErrorCode($mismatch), 'Idempotency mismatch code drifted.');

$fakeImage = molManagementImageFile('not an image', 'fake.png');
$unsupported = molManagementUploadRequest($chapterId, 'fake-image', $fakeImage);
molManagementIntegrationAssert(415 === $unsupported->get_status(), 'Fake image payload did not return 415.');
molManagementIntegrationAssert('mol_unsupported_media' === molManagementErrorCode($unsupported), 'Unsupported media code drifted.');
if (is_file($fakeImage['tmp_name'])) {
    unlink($fakeImage['tmp_name']);
}

$oneByte = static fn (int $size): int => 1;
add_filter('mol_max_page_upload_bytes', $oneByte);
$tooLargeFile = molManagementImageFile($imageBytes, 'too-large.png');
$tooLarge = molManagementUploadRequest($chapterId, 'too-large', $tooLargeFile);
remove_filter('mol_max_page_upload_bytes', $oneByte);
molManagementIntegrationAssert(413 === $tooLarge->get_status(), 'Oversized image did not return 413.');
molManagementIntegrationAssert('mol_payload_too_large' === molManagementErrorCode($tooLarge), 'Payload-too-large code drifted.');
if (is_file($tooLargeFile['tmp_name'])) {
    unlink($tooLargeFile['tmp_name']);
}

$jpegUpload = molManagementUploadRequest(
    $chapterId,
    'webp-source-jpeg',
    molManagementImageFile($jpegBytes, 'webp-source.jpg', 'image/jpeg')
);
molManagementIntegrationAssert(201 === $jpegUpload->get_status(), 'JPEG source upload failed.');
$jpegUploadBody = $jpegUpload->get_data();
$jpegAttachmentId = (int) $jpegUploadBody['data']['image']['attachment_id'];
$webpStatus = get_post_meta($jpegAttachmentId, MediaService::WEBP_STATUS_META_KEY, true);
if (wp_image_editor_supports(array('mime_type' => 'image/webp'))) {
    $webp = get_post_meta($jpegAttachmentId, MediaService::WEBP_META_KEY, true);
    molManagementIntegrationAssert(is_array($webp) && 'image/webp' === $webp['mime_type'], 'Supported JPEG-to-WebP derivative was not generated.');
    molManagementIntegrationAssert('generated' === $webpStatus, 'Generated WebP derivative status was not recorded.');
} else {
    molManagementIntegrationAssert('unsupported' === $webpStatus, 'Unavailable WebP fallback status was not recorded.');
}
$disableWebp = static fn (bool $enabled): bool => false;
add_filter('mol_generate_webp_derivative', $disableWebp);
$fallbackUpload = molManagementUploadRequest(
    $chapterId,
    'webp-fallback',
    molManagementImageFile($imageBytes, 'fallback.png')
);
remove_filter('mol_generate_webp_derivative', $disableWebp);
molManagementIntegrationAssert(201 === $fallbackUpload->get_status(), 'WebP-disabled fallback failed the upload.');
$fallbackBody = $fallbackUpload->get_data();
$fallbackAttachmentId = (int) $fallbackBody['data']['image']['attachment_id'];
molManagementIntegrationAssert('' === get_post_meta($fallbackAttachmentId, MediaService::WEBP_META_KEY, true), 'Disabled WebP derivative was generated.');
molManagementIntegrationAssert('disabled' === get_post_meta(
    $fallbackAttachmentId,
    MediaService::WEBP_STATUS_META_KEY,
    true
), 'Disabled WebP fallback status was not recorded.');

$rateUserId = molManagementCreateUser('mol_t06_rate_user', 'mol_t06_upload_only');
wp_set_current_user($rateUserId);
$oneUpload = static fn (int $limit): int => 1;
add_filter('mol_upload_rate_limit', $oneUpload);
$rateFirst = molManagementUploadRequest(
    $chapterId,
    'rate-first',
    molManagementImageFile($imageBytes, 'rate-01.png')
);
$rateSecondFile = molManagementImageFile($imageBytes, 'rate-02.png');
$rateSecond = molManagementUploadRequest($chapterId, 'rate-second', $rateSecondFile);
remove_filter('mol_upload_rate_limit', $oneUpload);
molManagementIntegrationAssert(201 === $rateFirst->get_status(), 'First rate-limited upload failed.');
molManagementIntegrationAssert(429 === $rateSecond->get_status(), 'Upload limiter did not return 429.');
molManagementIntegrationAssert('mol_rate_limited' === molManagementErrorCode($rateSecond), 'Rate-limit code drifted.');
molManagementIntegrationAssert(isset($rateSecond->get_headers()['Retry-After']), 'Rate limit response omitted Retry-After.');
if (is_file($rateSecondFile['tmp_name'])) {
    unlink($rateSecondFile['tmp_name']);
}

$currentPages = $pages->forChapter($chapterId);
$reversedIds = array_reverse(array_column($currentPages, 'id'));
wp_set_current_user($moderatorId);
$moderatorReorder = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId . '/pages/reorder', array(
    'page_ids' => $reversedIds,
));
molManagementIntegrationAssert(403 === $moderatorReorder->get_status(), 'Review capability granted page reorder.');
wp_set_current_user($administratorId);
$reordered = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId . '/pages/reorder', array(
    'page_ids' => $reversedIds,
));
molManagementIntegrationAssert(200 === $reordered->get_status(), 'Reverse page reorder failed.');
molManagementIntegrationAssert($reversedIds === array_column($pages->forChapter($chapterId), 'id'), 'Reverse page order did not persist.');
$beforeInvalidOrder = array_column($pages->forChapter($chapterId), 'id');
$invalidOrder = molManagementJsonRequest('PATCH', '/mol/v1/chapters/' . $chapterId . '/pages/reorder', array(
    'page_ids' => array_slice($beforeInvalidOrder, 1),
));
molManagementIntegrationAssert(400 === $invalidOrder->get_status(), 'Incomplete page permutation did not return 400.');
molManagementIntegrationAssert('mol_invalid_reorder' === molManagementErrorCode($invalidOrder), 'Invalid reorder code drifted.');
molManagementIntegrationAssert($beforeInvalidOrder === array_column($pages->forChapter($chapterId), 'id'), 'Invalid reorder partially changed page indices.');
$indicesBeforeRollback = array_column($pages->forChapter($chapterId), 'page_index');
$rollbackTransactions = new TransactionManager($wpdb);
try {
    $rollbackTransactions->run(static function () use ($pages, $chapterId): void {
        $locked = $pages->lockForChapter($chapterId);
        $maxIndex = max(array_column($locked, 'page_index'));
        $pages->moveToTemporaryRange($chapterId, $maxIndex + count($locked) + 2);
        throw new RuntimeException('Intentional page-order rollback');
    });
    throw new RuntimeException('Temporary page-order transaction did not throw.');
} catch (RuntimeException $error) {
    molManagementIntegrationAssert('Intentional page-order rollback' === $error->getMessage(), 'Page-order rollback replaced its cause.');
}
molManagementIntegrationAssert(
    $indicesBeforeRollback === array_column($pages->forChapter($chapterId), 'page_index'),
    'Page-order rollback left temporary indices behind.'
);

$protectedRequests = array(
    new WP_REST_Request('POST', '/mol/v1/chapters'),
    new WP_REST_Request('PATCH', '/mol/v1/chapters/' . $chapterId),
    new WP_REST_Request('DELETE', '/mol/v1/chapters/' . $chapterId),
    new WP_REST_Request('POST', '/mol/v1/chapters/' . $chapterId . '/pages'),
    new WP_REST_Request('PATCH', '/mol/v1/chapters/' . $chapterId . '/pages/reorder'),
    new WP_REST_Request('DELETE', '/mol/v1/pages/' . $uploadedPageId),
    new WP_REST_Request('PATCH', '/mol/v1/chapters/' . $chapterId . '/review'),
);
wp_set_current_user(0);
foreach ($protectedRequests as $request) {
    $response = rest_do_request($request);
    molManagementIntegrationAssert(401 === $response->get_status(), sprintf('%s did not enforce 401.', $request->get_route()));
}
wp_set_current_user($memberId);
foreach ($protectedRequests as $request) {
    $response = rest_do_request($request);
    molManagementIntegrationAssert(403 === $response->get_status(), sprintf('%s did not enforce 403.', $request->get_route()));
}

wp_set_current_user($administratorId);
$elements = new ElementRepository($wpdb);
$elementId = $elements->insert(array(
    'page_id' => $uploadedPageId,
    'element_type' => 'bubble',
    'x_unit' => 1000,
    'y_unit' => 1000,
    'w_unit' => 100000,
    'h_unit' => 100000,
    'content' => 'Cascade fixture',
    'style' => array('shape' => 'ellipse'),
    'created_by' => $administratorId,
));
$now = gmdate('Y-m-d H:i:s');
(new ElementLockRepository($wpdb))->insert($elementId, $administratorId, str_repeat('c', 64), $now, gmdate('Y-m-d H:i:s', time() + 300));
(new ContributionRepository($wpdb))->upsert($elementId, $administratorId, $workId, $chapterId, true, $now);
(new ReportRepository($wpdb))->insert(array(
    'chapter_id' => $chapterId,
    'page_id' => $uploadedPageId,
    'element_id' => $elementId,
    'reporter_id' => $administratorId,
    'report_type' => 'other',
    'message' => 'Page cascade fixture',
));
$deletedPage = rest_do_request(new WP_REST_Request('DELETE', '/mol/v1/pages/' . $uploadedPageId));
molManagementIntegrationAssert(204 === $deletedPage->get_status(), 'Page delete failed.');
$tables = new TableNames($wpdb->prefix);
molManagementIntegrationAssert(0 === (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables->elements} WHERE id = %d", $elementId)), 'Page delete left an element.');
molManagementIntegrationAssert(0 === (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables->elementLocks} WHERE element_id = %d", $elementId)), 'Page delete left a lock.');
molManagementIntegrationAssert(0 === (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables->contributions} WHERE element_id = %d", $elementId)), 'Page delete left a contribution.');
molManagementIntegrationAssert(0 === (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tables->reports} WHERE page_id = %d", $uploadedPageId)), 'Page delete left a report.');
molManagementIntegrationAssert(0 === (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables->idempotencyKeys} WHERE resource_type = 'page' AND resource_id = %d",
    $uploadedPageId
)), 'Page delete left an idempotency record.');
$remainingPages = $pages->forChapter($chapterId);
molManagementIntegrationAssert(range(0, count($remainingPages) - 1) === array_column($remainingPages, 'page_index'), 'Page delete did not compact indices.');

$cascadeChapter = molManagementJsonRequest('POST', '/mol/v1/chapters', array(
    'work_id' => $workId,
    'chapter_label' => 'cascade',
));
$cascadeBody = $cascadeChapter->get_data();
$cascadeChapterId = (int) $cascadeBody['data']['id'];
$attachmentId = wp_insert_attachment(array(
    'post_title' => 'Cascade attachment',
    'post_status' => 'inherit',
    'post_mime_type' => 'image/png',
), '', 0, true);
molManagementIntegrationAssert(is_int($attachmentId) && $attachmentId > 0, 'Could not create cascade attachment.');
$cascadePageId = $pages->insert($cascadeChapterId, 0, $attachmentId, 8, 8);
$cascadeElementId = $elements->insert(array(
    'page_id' => $cascadePageId,
    'element_type' => 'bubble',
    'x_unit' => 1000,
    'y_unit' => 1000,
    'w_unit' => 100000,
    'h_unit' => 100000,
    'content' => 'Chapter cascade fixture',
    'style' => array('shape' => 'ellipse'),
    'created_by' => $administratorId,
));
(new ElementLockRepository($wpdb))->insert($cascadeElementId, $administratorId, str_repeat('d', 64), $now, gmdate('Y-m-d H:i:s', time() + 300));
(new ContributionRepository($wpdb))->upsert($cascadeElementId, $administratorId, $workId, $cascadeChapterId, true, $now);
(new ReportRepository($wpdb))->insert(array(
    'chapter_id' => $cascadeChapterId,
    'page_id' => $cascadePageId,
    'element_id' => $cascadeElementId,
    'reporter_id' => $administratorId,
    'report_type' => 'other',
    'message' => 'Chapter cascade fixture',
));
(new ReadingProgressRepository($wpdb))->upsert($administratorId, $cascadeChapterId, 0, 500000, 'webtoon', $now);
(new IdempotencyKeyRepository($wpdb))->insert(array(
    'user_id' => $administratorId,
    'scope' => 'page-upload:' . $cascadeChapterId,
    'idempotency_key' => 'cascade-key',
    'request_hash' => hash('sha256', 'cascade'),
    'resource_type' => 'page',
    'resource_id' => $cascadePageId,
    'response_code' => 201,
    'response' => array('page_id' => $cascadePageId),
    'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
));
$deletedChapter = rest_do_request(new WP_REST_Request('DELETE', '/mol/v1/chapters/' . $cascadeChapterId));
molManagementIntegrationAssert(204 === $deletedChapter->get_status(), 'Chapter delete failed.');
foreach (array(
    $tables->chapters => array('id', $cascadeChapterId),
    $tables->pages => array('chapter_id', $cascadeChapterId),
    $tables->elements => array('id', $cascadeElementId),
    $tables->elementLocks => array('element_id', $cascadeElementId),
    $tables->contributions => array('chapter_id', $cascadeChapterId),
    $tables->reports => array('chapter_id', $cascadeChapterId),
    $tables->readingProgress => array('chapter_id', $cascadeChapterId),
) as $table => [$field, $value]) {
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$field} = %d", $value));
    molManagementIntegrationAssert(0 === $count, sprintf('Chapter delete left rows in %s.', $table));
}
$idempotencyCount = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tables->idempotencyKeys} WHERE scope = %s",
    'page-upload:' . $cascadeChapterId
));
molManagementIntegrationAssert(0 === $idempotencyCount, 'Chapter delete left idempotency records.');

$missingChapterDelete = rest_do_request(new WP_REST_Request('DELETE', '/mol/v1/chapters/' . $cascadeChapterId));
molManagementIntegrationAssert(404 === $missingChapterDelete->get_status(), 'Deleting a missing chapter did not return 404.');

echo "Manga Overlay content-management integration passed.\n";
