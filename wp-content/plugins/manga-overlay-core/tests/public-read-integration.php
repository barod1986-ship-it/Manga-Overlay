<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Content\WorkContent;
use MOL\Content\WorkMeta;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReadingProgressRepository;

function molPublicIntegrationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $query */
function molPublicIntegrationGet(string $path, array $query = array()): WP_REST_Response
{
    $request = new WP_REST_Request('GET', $path);
    $request->set_query_params($query);

    return rest_do_request($request);
}

/** @param array<string, mixed> $payload */
function molPublicIntegrationPut(string $path, array $payload): WP_REST_Response
{
    $request = new WP_REST_Request('PUT', $path);
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode($payload));

    return rest_do_request($request);
}

function molPublicIntegrationErrorCode(WP_REST_Response $response): string
{
    $data = $response->get_data();

    return is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
}

function molPublicIntegrationCreateUser(string $username, string $role): int
{
    $userId = wp_create_user($username, wp_generate_password(32), $username . '@example.invalid');
    molPublicIntegrationAssert(! is_wp_error($userId), sprintf('Could not create %s.', $username));
    $user = get_user_by('id', $userId);
    molPublicIntegrationAssert($user instanceof WP_User, sprintf('Could not load %s.', $username));
    $user->set_role($role);

    return (int) $userId;
}

function molPublicIntegrationTerm(string $taxonomy, string $slug, string $name): int
{
    $existing = term_exists($slug, $taxonomy);
    if (is_array($existing)) {
        return (int) $existing['term_id'];
    }
    if (is_int($existing)) {
        return $existing;
    }
    $created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
    molPublicIntegrationAssert(! is_wp_error($created), sprintf('Could not create %s.', $slug));

    return (int) $created['term_id'];
}

/** @param array<string, string|list<string>> $taxonomies */
function molPublicIntegrationWork(string $title, string $status, string $date, array $taxonomies): int
{
    $workId = wp_insert_post(array(
        'post_type' => WorkContent::POST_TYPE,
        'post_status' => $status,
        'post_title' => $title,
        'post_content' => '<p>Description for ' . esc_html($title) . '.</p>',
        'post_date' => $date,
        'post_date_gmt' => $date,
    ), true);
    molPublicIntegrationAssert(is_int($workId) && $workId > 0, sprintf('Could not create %s.', $title));
    foreach ($taxonomies as $taxonomy => $terms) {
        $assigned = wp_set_object_terms($workId, (array) $terms, $taxonomy);
        molPublicIntegrationAssert(! is_wp_error($assigned), sprintf('Could not assign %s.', $taxonomy));
    }

    return $workId;
}

function molPublicIntegrationAttachment(string $filename): int
{
    $attachmentId = wp_insert_attachment(array(
        'post_title' => $filename,
        'post_status' => 'inherit',
        'post_mime_type' => 'image/png',
    ), '', 0, true);
    molPublicIntegrationAssert(is_int($attachmentId) && $attachmentId > 0, 'Could not create an attachment.');
    update_post_meta($attachmentId, '_wp_attached_file', '2026/09/' . $filename);

    return $attachmentId;
}

/** @param list<array<string, mixed>> $items @return list<int> */
function molPublicIntegrationIds(array $items): array
{
    return array_map(static fn (array $item): int => (int) $item['id'], $items);
}

global $wpdb;
molPublicIntegrationAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
$administratorId = get_current_user_id();
molPublicIntegrationAssert($administratorId > 0, 'Run the public-read integration test as an administrator.');

$routes = rest_get_server()->get_routes();
foreach (array(
    '/mol/v1/library',
    '/mol/v1/capabilities',
    '/mol/v1/works/(?P<id>\d+)',
    '/mol/v1/works/(?P<id>\d+)/chapters',
    '/mol/v1/chapters/(?P<id>\d+)',
    '/mol/v1/chapters/(?P<id>\d+)/pages',
    '/mol/v1/pages/(?P<id>\d+)/elements',
    '/mol/v1/chapters/(?P<id>\d+)/elements',
    '/mol/v1/chapters/(?P<id>\d+)/contributors',
    '/mol/v1/profiles/(?P<username>[^/]+)',
    '/mol/v1/reading-progress',
) as $route) {
    molPublicIntegrationAssert(isset($routes[$route]), sprintf('Public REST route %s is missing.', $route));
}

molPublicIntegrationTerm(WorkContent::GENRE_TAXONOMY, 't07-library', 'T07 Library');
molPublicIntegrationTerm(WorkContent::GENRE_TAXONOMY, 't07-action', 'T07 Action');
molPublicIntegrationTerm(WorkContent::SOURCE_LANGUAGE_TAXONOMY, 'ja', 'Japanese');
molPublicIntegrationTerm(WorkContent::SOURCE_LANGUAGE_TAXONOMY, 'ko', 'Korean');
molPublicIntegrationTerm(WorkContent::WORK_STATUS_TAXONOMY, 'ongoing', 'Ongoing');
molPublicIntegrationTerm(WorkContent::WORK_STATUS_TAXONOMY, 'completed', 'Completed');

$workAlpha = molPublicIntegrationWork('T07 Alpha Work', 'publish', '2026-01-01 00:00:00', array(
    WorkContent::WORK_TYPE_TAXONOMY => 'manga',
    WorkContent::GENRE_TAXONOMY => array('t07-library', 't07-action'),
    WorkContent::SOURCE_LANGUAGE_TAXONOMY => 'ja',
    WorkContent::WORK_STATUS_TAXONOMY => 'ongoing',
));
$workBeta = molPublicIntegrationWork('T07 Beta Work', 'publish', '2026-01-02 00:00:00', array(
    WorkContent::WORK_TYPE_TAXONOMY => 'manhwa',
    WorkContent::GENRE_TAXONOMY => 't07-library',
    WorkContent::SOURCE_LANGUAGE_TAXONOMY => 'ko',
    WorkContent::WORK_STATUS_TAXONOMY => 'completed',
));
$draftWork = molPublicIntegrationWork('T07 Draft Work', 'draft', '2026-01-03 00:00:00', array(
    WorkContent::WORK_TYPE_TAXONOMY => 'manga',
    WorkContent::GENRE_TAXONOMY => 't07-library',
));
update_post_meta($workAlpha, WorkMeta::ALT_TITLES, array('Alpha Alias'));
update_post_meta($workAlpha, WorkMeta::DEFAULT_READER_MODE, 'paged');
update_post_meta($workAlpha, WorkMeta::READING_DIRECTION, 'rtl');

$chapters = new ChapterRepository($wpdb);
$pages = new PageRepository($wpdb);
$elements = new ElementRepository($wpdb);
$contributions = new ContributionRepository($wpdb);
$publishedAlpha = $chapters->insert(array(
    'work_id' => $workAlpha,
    'chapter_label' => '1',
    'sort_order' => 1,
    'title' => 'Alpha One',
    'slug' => 'alpha-one',
    'translation_status' => 'completed',
    'is_published' => true,
    'published_at' => '2026-01-03 00:00:00',
    'created_by' => $administratorId,
));
$publishedAlphaTwo = $chapters->insert(array(
    'work_id' => $workAlpha,
    'chapter_label' => '2',
    'sort_order' => 2,
    'title' => 'Alpha Two',
    'slug' => 'alpha-two',
    'translation_status' => 'needs_review',
    'is_published' => true,
    'published_at' => '2026-01-05 00:00:00',
    'created_by' => $administratorId,
));
$draftAlpha = $chapters->insert(array(
    'work_id' => $workAlpha,
    'chapter_label' => 'draft',
    'sort_order' => 3,
    'title' => 'Alpha Draft',
    'slug' => 'alpha-draft',
    'translation_status' => 'in_progress',
    'is_published' => false,
    'created_by' => $administratorId,
));
$publishedBeta = $chapters->insert(array(
    'work_id' => $workBeta,
    'chapter_label' => '1',
    'sort_order' => 1,
    'title' => 'Beta One',
    'slug' => 'beta-one',
    'translation_status' => 'untranslated',
    'is_published' => true,
    'published_at' => '2026-01-06 00:00:00',
    'created_by' => $administratorId,
));
$draftWorkChapter = $chapters->insert(array(
    'work_id' => $draftWork,
    'chapter_label' => '1',
    'slug' => 'draft-work-one',
    'is_published' => true,
    'published_at' => '2026-01-07 00:00:00',
    'created_by' => $administratorId,
));

$translatorId = molPublicIntegrationCreateUser('mol_t07_translator', 'mol_translator');
$memberId = molPublicIntegrationCreateUser('mol_t07_member', 'mol_member');
wp_update_user(array(
    'ID' => $translatorId,
    'display_name' => 'T07 Translator',
    'description' => 'Public translator biography.',
));
update_user_meta($translatorId, 'mol_profile_tag', 'Arabic translator');

$alphaPageOne = $pages->insert(
    $publishedAlpha,
    0,
    molPublicIntegrationAttachment('t07-alpha-1.png'),
    800,
    1200
);
$alphaPageTwo = $pages->insert(
    $publishedAlpha,
    1,
    molPublicIntegrationAttachment('t07-alpha-2.png'),
    900,
    1400
);
$draftPage = $pages->insert(
    $draftAlpha,
    0,
    molPublicIntegrationAttachment('t07-draft.png'),
    800,
    1200
);
$elementOne = $elements->insert(array(
    'page_id' => $alphaPageOne,
    'target_lang' => 'ar',
    'element_type' => 'bubble',
    'x_unit' => 100000,
    'y_unit' => 100000,
    'w_unit' => 300000,
    'h_unit' => 200000,
    'content' => 'العنصر الأول',
    'style' => array('shape' => 'ellipse'),
    'version' => 2,
    'created_by' => $translatorId,
));
$elementTwo = $elements->insert(array(
    'page_id' => $alphaPageTwo,
    'target_lang' => 'ar',
    'element_type' => 'free_text',
    'x_unit' => 200000,
    'y_unit' => 200000,
    'w_unit' => 200000,
    'h_unit' => 100000,
    'content' => 'العنصر الثاني',
    'style' => array(),
    'created_by' => $translatorId,
));
$englishElement = $elements->insert(array(
    'page_id' => $alphaPageOne,
    'target_lang' => 'en',
    'element_type' => 'free_text',
    'x_unit' => 100000,
    'y_unit' => 400000,
    'w_unit' => 300000,
    'h_unit' => 100000,
    'content' => 'English overlay',
    'style' => array(),
    'created_by' => $administratorId,
));
$draftElement = $elements->insert(array(
    'page_id' => $draftPage,
    'target_lang' => 'ar',
    'element_type' => 'bubble',
    'x_unit' => 100000,
    'y_unit' => 100000,
    'w_unit' => 300000,
    'h_unit' => 200000,
    'content' => 'Draft overlay',
    'style' => array('shape' => 'ellipse'),
    'created_by' => $translatorId,
));
$contributions->upsert($elementOne, $translatorId, $workAlpha, $publishedAlpha, true, '2026-01-03 01:00:00');
$contributions->upsert($elementTwo, $translatorId, $workAlpha, $publishedAlpha, true, '2026-01-03 02:00:00');
$contributions->upsert($elementOne, $administratorId, $workAlpha, $publishedAlpha, false, '2026-01-03 03:00:00');
$contributions->upsert($englishElement, $administratorId, $workAlpha, $publishedAlpha, true, '2026-01-03 04:00:00');
$contributions->upsert($draftElement, $translatorId, $workAlpha, $draftAlpha, true, '2026-01-04 01:00:00');

wp_set_current_user(0);
$capabilities = molPublicIntegrationGet('/mol/v1/capabilities');
molPublicIntegrationAssert(200 === $capabilities->get_status(), 'Capabilities route failed publicly.');
$capabilitiesBody = $capabilities->get_data();
molPublicIntegrationAssert(false === $capabilitiesBody['data']['most_read_available'], 'Read-counter capability drifted.');
foreach (array('image/jpeg', 'image/png', 'image/webp') as $mime) {
    molPublicIntegrationAssert(in_array($mime, $capabilitiesBody['data']['upload_mime_types'], true), sprintf('%s is missing.', $mime));
}

$latest = molPublicIntegrationGet('/mol/v1/library', array(
    'genre' => array('t07-library'),
    'sort' => 'latest_chapter',
    'per_page' => 10,
));
molPublicIntegrationAssert(200 === $latest->get_status(), 'Library latest-chapter query failed.');
$latestBody = $latest->get_data();
molPublicIntegrationAssert(2 === $latestBody['meta']['total'], 'Draft work leaked into the library total.');
molPublicIntegrationAssert(
    array($workBeta, $workAlpha) === molPublicIntegrationIds($latestBody['data']),
    'latest_chapter ordering drifted.'
);
molPublicIntegrationAssert(
    str_contains((string) ($latest->get_headers()['Cache-Control'] ?? ''), 'public'),
    'Public library response is not cacheable.'
);
molPublicIntegrationAssert(isset($latest->get_headers()['ETag']), 'Library response omitted ETag.');

$titleAscending = molPublicIntegrationGet('/mol/v1/library', array(
    'genre' => array('t07-library'),
    'sort' => 'title_asc',
));
molPublicIntegrationAssert(
    array($workAlpha, $workBeta) === molPublicIntegrationIds($titleAscending->get_data()['data']),
    'title_asc ordering drifted.'
);
$pageTwo = molPublicIntegrationGet('/mol/v1/library', array(
    'genre' => array('t07-library'),
    'sort' => 'title_asc',
    'page' => 2,
    'per_page' => 1,
));
$pageTwoBody = $pageTwo->get_data();
molPublicIntegrationAssert(2 === $pageTwoBody['meta']['total'], 'Library pagination total drifted.');
molPublicIntegrationAssert(2 === $pageTwoBody['meta']['total_pages'], 'Library total_pages drifted.');
molPublicIntegrationAssert(array($workBeta) === molPublicIntegrationIds($pageTwoBody['data']), 'Library page 2 drifted.');

$filtered = molPublicIntegrationGet('/mol/v1/library', array(
    'search' => 'Alpha',
    'type' => 'manga',
    'genre' => array('t07-library', 't07-action'),
    'source_lang' => 'ja',
    'work_status' => 'ongoing',
    'translation_status' => 'completed',
    'sort' => 'latest_work',
));
molPublicIntegrationAssert(
    array($workAlpha) === molPublicIntegrationIds($filtered->get_data()['data']),
    'Combined library filters did not select Alpha.'
);
$draftStatusFilter = molPublicIntegrationGet('/mol/v1/library', array(
    'genre' => array('t07-library'),
    'translation_status' => 'in_progress',
));
molPublicIntegrationAssert(0 === $draftStatusFilter->get_data()['meta']['total'], 'Draft translation status leaked into library filters.');

$unavailableSort = molPublicIntegrationGet('/mol/v1/library', array('sort' => 'most_read'));
molPublicIntegrationAssert(400 === $unavailableSort->get_status(), 'most_read did not return 400.');
molPublicIntegrationAssert('mol_sort_unavailable' === molPublicIntegrationErrorCode($unavailableSort), 'most_read error code drifted.');
$invalidPagination = molPublicIntegrationGet('/mol/v1/library', array('per_page' => 101));
molPublicIntegrationAssert(400 === $invalidPagination->get_status(), 'Invalid per_page did not return 400.');
molPublicIntegrationAssert('mol_invalid_params' === molPublicIntegrationErrorCode($invalidPagination), 'Pagination error code drifted.');

$workResponse = molPublicIntegrationGet('/mol/v1/works/' . $workAlpha);
molPublicIntegrationAssert(200 === $workResponse->get_status(), 'Published work detail failed.');
$workBody = $workResponse->get_data();
molPublicIntegrationAssert('manga' === $workBody['data']['type'], 'Work type DTO drifted.');
molPublicIntegrationAssert(array('Alpha Alias') === $workBody['data']['alt_titles'], 'Alternative titles drifted.');
molPublicIntegrationAssert(2 === $workBody['data']['translation_summary']['total'], 'Published chapter summary drifted.');
molPublicIntegrationAssert(null === $workBody['data']['read_count'], 'MVP work read_count must be null.');
molPublicIntegrationAssert('' !== $workBody['data']['cover']['url'], 'Cover fallback URL is empty.');
molPublicIntegrationAssert(404 === molPublicIntegrationGet('/mol/v1/works/' . $draftWork)->get_status(), 'Draft work detail leaked publicly.');

$chapterList = molPublicIntegrationGet('/mol/v1/works/' . $workAlpha . '/chapters', array(
    'page' => 1,
    'per_page' => 1,
));
$chapterListBody = $chapterList->get_data();
molPublicIntegrationAssert(200 === $chapterList->get_status(), 'Published chapter list failed.');
molPublicIntegrationAssert(2 === $chapterListBody['meta']['total'], 'Draft chapter leaked into chapter total.');
molPublicIntegrationAssert(2 === $chapterListBody['meta']['total_pages'], 'Chapter pagination drifted.');
molPublicIntegrationAssert($publishedAlpha === (int) $chapterListBody['data'][0]['id'], 'Chapter sort order drifted.');

$chapterResponse = molPublicIntegrationGet('/mol/v1/chapters/' . $publishedAlpha);
molPublicIntegrationAssert(200 === $chapterResponse->get_status(), 'Published chapter was not public.');
molPublicIntegrationAssert(is_float($chapterResponse->get_data()['data']['sort_order']), 'Chapter sort_order is not numeric.');
$pageResponse = molPublicIntegrationGet('/mol/v1/chapters/' . $publishedAlpha . '/pages');
$pageBody = $pageResponse->get_data();
molPublicIntegrationAssert(200 === $pageResponse->get_status(), 'Published pages were not public.');
molPublicIntegrationAssert(2 === $pageBody['meta']['count'], 'Published page count drifted.');
molPublicIntegrationAssert('' !== $pageBody['data'][0]['image']['url'], 'Page image URL is empty.');

$pageElements = molPublicIntegrationGet('/mol/v1/pages/' . $alphaPageOne . '/elements', array('lang' => 'ar'));
$pageElementsBody = $pageElements->get_data();
molPublicIntegrationAssert(200 === $pageElements->get_status(), 'Page elements route failed.');
molPublicIntegrationAssert(array($elementOne) === molPublicIntegrationIds($pageElementsBody['data']), 'Page language filter drifted.');
molPublicIntegrationAssert(2 === $pageElementsBody['data'][0]['version'], 'Element version is missing.');
molPublicIntegrationAssert(isset($pageElements->get_headers()['ETag']), 'Page elements omitted ETag.');
$englishElements = molPublicIntegrationGet('/mol/v1/pages/' . $alphaPageOne . '/elements', array('lang' => 'en'));
molPublicIntegrationAssert(
    array($englishElement) === molPublicIntegrationIds($englishElements->get_data()['data']),
    'English element filter drifted.'
);

$chapterElements = molPublicIntegrationGet('/mol/v1/chapters/' . $publishedAlpha . '/elements', array('lang' => 'ar'));
$chapterElementsBody = $chapterElements->get_data();
molPublicIntegrationAssert(200 === $chapterElements->get_status(), 'Chapter overlay batch failed.');
molPublicIntegrationAssert(2 === $chapterElementsBody['meta']['page_count'], 'Chapter batch omitted an empty/non-empty page.');
molPublicIntegrationAssert(2 === $chapterElementsBody['meta']['element_count'], 'Chapter batch element count drifted.');
$batchIds = array();
foreach ($chapterElementsBody['data'] as $group) {
    array_push($batchIds, ...molPublicIntegrationIds($group['elements']));
}
sort($batchIds);
$unionIds = array($elementOne, $elementTwo);
sort($unionIds);
molPublicIntegrationAssert($unionIds === $batchIds, 'Chapter batch does not equal the union of page elements.');

$contributorsResponse = molPublicIntegrationGet('/mol/v1/chapters/' . $publishedAlpha . '/contributors');
$contributorsBody = $contributorsResponse->get_data();
molPublicIntegrationAssert(200 === $contributorsResponse->get_status(), 'Chapter contributors failed.');
molPublicIntegrationAssert(2 === $contributorsBody['meta']['count'], 'Contributor count drifted.');
$translatorContributors = array_values(array_filter(
    $contributorsBody['data'],
    static fn (array $item): bool => $translatorId === (int) $item['user_id']
));
molPublicIntegrationAssert(2 === (int) $translatorContributors[0]['element_count'], 'Unique contributor count drifted.');

molPublicIntegrationAssert(function_exists('mol_get_work_chapters'), 'Public PHP work-chapter API is missing.');
molPublicIntegrationAssert(2 === count(mol_get_work_chapters($workAlpha)), 'Public PHP chapters leaked a draft or missed data.');
molPublicIntegrationAssert(2 === count(mol_get_chapter_pages($publishedAlpha)), 'Public PHP page API drifted.');
molPublicIntegrationAssert(1 === count(mol_get_page_elements($alphaPageOne, 'ar')), 'Public PHP page-elements API drifted.');
molPublicIntegrationAssert(2 === count(mol_get_chapter_elements($publishedAlpha, 'ar')), 'Public PHP chapter-elements API drifted.');
molPublicIntegrationAssert(2 === count(mol_get_chapter_contributors($publishedAlpha)), 'Public PHP contributors API drifted.');
molPublicIntegrationAssert(function_exists('mol_get_reading_progress'), 'Reading-progress PHP API is missing.');

$anonymousProgress = molPublicIntegrationPut('/mol/v1/reading-progress', array(
    'chapter_id' => $publishedAlpha,
    'page_index' => 1,
    'progress_unit' => 500000,
    'reader_mode' => 'paged',
));
molPublicIntegrationAssert(401 === $anonymousProgress->get_status(), 'Anonymous progress save was not rejected.');

$draftRoutes = array(
    '/mol/v1/chapters/' . $draftAlpha,
    '/mol/v1/chapters/' . $draftAlpha . '/pages',
    '/mol/v1/chapters/' . $draftAlpha . '/elements?lang=ar',
    '/mol/v1/pages/' . $draftPage . '/elements?lang=ar',
    '/mol/v1/chapters/' . $draftAlpha . '/contributors',
    '/mol/v1/chapters/' . $draftWorkChapter,
);
foreach ($draftRoutes as $route) {
    $parts = wp_parse_url($route);
    $path = (string) ($parts['path'] ?? $route);
    parse_str((string) ($parts['query'] ?? ''), $query);
    $response = molPublicIntegrationGet($path, $query);
    molPublicIntegrationAssert(404 === $response->get_status(), sprintf('Draft route %s leaked publicly.', $route));
    molPublicIntegrationAssert('mol_not_found' === molPublicIntegrationErrorCode($response), 'Draft visibility code drifted.');
}
molPublicIntegrationAssert(null === mol_get_chapter($draftAlpha), 'Public PHP API leaked a draft anonymously.');

wp_set_current_user($memberId);
$savedProgress = molPublicIntegrationPut('/mol/v1/reading-progress', array(
    'chapter_id' => $publishedAlpha,
    'page_index' => 1,
    'progress_unit' => 500000,
    'reader_mode' => 'paged',
));
$savedProgressBody = $savedProgress->get_data();
molPublicIntegrationAssert(200 === $savedProgress->get_status(), 'Member reading progress was not saved.');
molPublicIntegrationAssert(1 === $savedProgressBody['data']['page_index'], 'Saved progress page index drifted.');
molPublicIntegrationAssert(500000 === $savedProgressBody['data']['progress_unit'], 'Saved progress unit drifted.');
molPublicIntegrationAssert('paged' === $savedProgressBody['data']['reader_mode'], 'Saved reader mode drifted.');
$progressRepository = new ReadingProgressRepository($wpdb);
$storedProgress = $progressRepository->find($memberId, $publishedAlpha);
molPublicIntegrationAssert(1 === (int) ($storedProgress['page_index'] ?? -1), 'Progress repository did not persist the page.');
$themeProgress = mol_get_reading_progress($memberId, $publishedAlpha);
molPublicIntegrationAssert(1 === (int) ($themeProgress['page_index'] ?? -1), 'Progress PHP API drifted.');

$invalidProgress = molPublicIntegrationPut('/mol/v1/reading-progress', array(
    'chapter_id' => $publishedAlpha,
    'page_index' => 2,
    'progress_unit' => 0,
    'reader_mode' => 'webtoon',
));
molPublicIntegrationAssert(400 === $invalidProgress->get_status(), 'Out-of-range reading progress was accepted.');
$draftProgress = molPublicIntegrationPut('/mol/v1/reading-progress', array(
    'chapter_id' => $draftAlpha,
    'page_index' => 0,
    'progress_unit' => 0,
    'reader_mode' => 'webtoon',
));
molPublicIntegrationAssert(404 === $draftProgress->get_status(), 'Member saved progress for a hidden draft.');
molPublicIntegrationAssert(
    404 === molPublicIntegrationGet('/mol/v1/chapters/' . $draftAlpha)->get_status(),
    'Member learned that a draft chapter exists.'
);
molPublicIntegrationAssert(! mol_user_can_edit_chapter($memberId, $draftAlpha), 'Member passed public edit policy.');

wp_set_current_user($translatorId);
foreach (array(
    '/mol/v1/chapters/' . $draftAlpha,
    '/mol/v1/chapters/' . $draftAlpha . '/pages',
    '/mol/v1/chapters/' . $draftAlpha . '/elements',
    '/mol/v1/pages/' . $draftPage . '/elements',
    '/mol/v1/chapters/' . $draftAlpha . '/contributors',
) as $route) {
    $response = molPublicIntegrationGet($route);
    molPublicIntegrationAssert(200 === $response->get_status(), sprintf('Editor could not read %s.', $route));
    molPublicIntegrationAssert(
        str_contains((string) ($response->get_headers()['Cache-Control'] ?? ''), 'no-store'),
        sprintf('Draft route %s was cacheable.', $route)
    );
}
molPublicIntegrationAssert(null !== mol_get_chapter($draftAlpha), 'Editor public PHP API could not read a draft.');
molPublicIntegrationAssert(mol_user_can_edit_chapter($translatorId, $draftAlpha), 'Translator failed edit policy.');
$authenticatedCollection = molPublicIntegrationGet('/mol/v1/works/' . $workAlpha . '/chapters', array('per_page' => 100));
molPublicIntegrationAssert(2 === $authenticatedCollection->get_data()['meta']['total'], 'Authenticated collection leaked drafts.');

wp_set_current_user(0);
$profile = molPublicIntegrationGet('/mol/v1/profiles/mol_t07_translator');
$profileBody = $profile->get_data();
molPublicIntegrationAssert(200 === $profile->get_status(), 'Public profile failed.');
molPublicIntegrationAssert('Arabic translator' === $profileBody['data']['profile_tag'], 'Profile tag drifted.');
molPublicIntegrationAssert(1 === $profileBody['data']['stats']['works'], 'Profile work stats included a draft.');
molPublicIntegrationAssert(1 === $profileBody['data']['stats']['chapters'], 'Profile chapter stats included a draft.');
molPublicIntegrationAssert(2 === $profileBody['data']['stats']['elements'], 'Profile element stats included a draft.');
molPublicIntegrationAssert(1 === count($profileBody['data']['recent_contributions']), 'Recent contributions were not grouped.');
molPublicIntegrationAssert(
    404 === molPublicIntegrationGet('/mol/v1/profiles/mol_t07_missing')->get_status(),
    'Missing profile did not return 404.'
);
molPublicIntegrationAssert(
    404 === molPublicIntegrationGet('/mol/v1/chapters/999999999')->get_status(),
    'Missing chapter did not return 404.'
);
molPublicIntegrationAssert(
    404 === molPublicIntegrationGet('/mol/v1/pages/999999999/elements')->get_status(),
    'Missing page did not return 404.'
);

echo "Manga Overlay public-read integration passed.\n";
