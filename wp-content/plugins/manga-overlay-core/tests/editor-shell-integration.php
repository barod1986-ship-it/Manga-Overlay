<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Content\WorkContent;
use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Frontend\EditorPage;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\WorkRepository;
use MOL\REST\ApiException;
use MOL\Services\EditorContextService;

function molEditorIntegrationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function molEditorIntegrationUser(string $username, string $role): int
{
    $userId = wp_create_user($username, wp_generate_password(32), $username . '@example.invalid');
    molEditorIntegrationAssert(! is_wp_error($userId), sprintf('Could not create %s.', $username));
    $user = get_user_by('id', $userId);
    molEditorIntegrationAssert($user instanceof WP_User, sprintf('Could not load %s.', $username));
    $user->set_role($role);

    return (int) $userId;
}

function molEditorIntegrationAttachment(string $filename): int
{
    $attachmentId = wp_insert_attachment(array(
        'post_title' => $filename,
        'post_status' => 'inherit',
        'post_mime_type' => 'image/png',
    ), '', 0, true);
    molEditorIntegrationAssert(is_int($attachmentId) && $attachmentId > 0, 'Could not create an attachment.');
    update_post_meta($attachmentId, '_wp_attached_file', '2026/09/' . $filename);
    update_post_meta($attachmentId, '_wp_attachment_image_alt', 'T10 editor fixture');

    return $attachmentId;
}

global $wpdb, $wp_query, $wp_the_query, $post;
molEditorIntegrationAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
$administratorId = get_current_user_id();
molEditorIntegrationAssert($administratorId > 0, 'Run the editor integration test as an administrator.');

$workId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T10 محرر العمل',
    'post_name' => 't10-editor-work',
), true);
molEditorIntegrationAssert(is_int($workId) && $workId > 0, 'Could not create the editor work.');

$chapters = new ChapterRepository($wpdb);
$pages = new PageRepository($wpdb);
$elements = new ElementRepository($wpdb);
$chapterId = $chapters->insert(array(
    'work_id' => $workId,
    'chapter_label' => 'مسودة',
    'sort_order' => 1,
    'title' => 'فصل T10',
    'slug' => 't10-draft',
    'translation_status' => 'in_progress',
    'is_published' => false,
    'created_by' => $administratorId,
));
$pageOne = $pages->insert($chapterId, 0, molEditorIntegrationAttachment('t10-editor-1.png'), 800, 1200);
$pageTwo = $pages->insert($chapterId, 1, molEditorIntegrationAttachment('t10-editor-2.png'), 900, 1400);
$elementId = $elements->insert(array(
    'page_id' => $pageOne,
    'target_lang' => 'ar',
    'element_type' => 'free_text',
    'x_unit' => 100000,
    'y_unit' => 200000,
    'w_unit' => 300000,
    'h_unit' => 150000,
    'rotation_mdeg' => 5000,
    'z_index' => 2,
    'content' => '</script><img src=x onerror=alert(1)>نص آمن',
    'style' => array(),
    'created_by' => $administratorId,
));
$elements->insert(array(
    'page_id' => $pageTwo,
    'target_lang' => 'en',
    'element_type' => 'free_text',
    'x_unit' => 100000,
    'y_unit' => 100000,
    'w_unit' => 200000,
    'h_unit' => 100000,
    'content' => 'Not part of the Arabic editor context',
    'style' => array(),
    'created_by' => $administratorId,
));

$translatorId = molEditorIntegrationUser('mol_t10_translator', 'mol_translator');
$memberId = molEditorIntegrationUser('mol_t10_member', 'mol_member');
$service = new EditorContextService(
    new WorkRepository($wpdb),
    $chapters,
    $pages,
    $elements,
    new ChapterVisibilityPolicy()
);

wp_set_current_user($memberId);
try {
    $service->load($workId, 't10-draft', $memberId);
    throw new RuntimeException('A member loaded the editor context.');
} catch (ApiException $error) {
    molEditorIntegrationAssert(403 === $error->status(), 'Unauthorized editor context did not return 403.');
}

wp_set_current_user($translatorId);
molEditorIntegrationAssert(current_user_can('mol_use_editor'), 'Translator lacks mol_use_editor.');
$context = $service->load($workId, 't10-draft', $translatorId);
molEditorIntegrationAssert($chapterId === (int) $context['chapter']['id'], 'Draft chapter was not loaded.');
molEditorIntegrationAssert(2 === count($context['pages']), 'Editor context page count drifted.');
molEditorIntegrationAssert(
    array($elementId) === array_map(
        static fn (array $element): int => (int) $element['id'],
        $context['pages'][0]['elements']
    ),
    'Arabic page elements were not grouped correctly.'
);
molEditorIntegrationAssert(array() === $context['pages'][1]['elements'], 'A different target language leaked into context.');

try {
    $service->load($workId, 'missing-draft', $translatorId);
    throw new RuntimeException('A missing chapter loaded in the editor.');
} catch (ApiException $error) {
    molEditorIntegrationAssert(404 === $error->status(), 'Missing editor chapter did not return 404.');
}

$editorQuery = new WP_Query(array(
    'post_type' => WorkContent::POST_TYPE,
    'p' => $workId,
    'mol_chapter' => 't10-draft',
    'mol_editor' => '1',
));
$wp_query = $editorQuery;
$wp_the_query = $editorQuery;
$post = $editorQuery->post;
if ($post instanceof WP_Post) {
    setup_postdata($post);
}
molEditorIntegrationAssert(EditorPage::isEditorRequest(), 'Canonical /edit/ query was not recognized.');
$resolvedTemplate = apply_filters('template_include', get_stylesheet_directory() . '/single.php');
molEditorIntegrationAssert(
    MOL_PLUGIN_DIR . 'templates/editor.php' === $resolvedTemplate,
    'Editor request did not resolve to the plugin template.'
);

$editorPage = new EditorPage();
$editorPage->enqueueAssets();
molEditorIntegrationAssert(wp_style_is('mol-editor', 'enqueued'), 'Editor stylesheet was not enqueued.');
molEditorIntegrationAssert(wp_script_is('mol-editor', 'enqueued'), 'Editor script was not enqueued.');
$registeredScript = wp_scripts()->registered['mol-editor'] ?? null;
molEditorIntegrationAssert($registeredScript instanceof _WP_Dependency, 'Editor script was not registered.');
molEditorIntegrationAssert(MOL_PLUGIN_VERSION === $registeredScript->ver, 'Editor asset version drifted.');

$bootstrap = EditorPage::bootstrap();
molEditorIntegrationAssert('0.12.0' === $bootstrap['release']['core'], 'Editor bootstrap release drifted.');
molEditorIntegrationAssert(false === $bootstrap['permissions']['manageWorkPresets'], 'Translator gained work-preset management.');
molEditorIntegrationAssert(false === $bootstrap['permissions']['manageGlobalPresets'], 'Translator gained global-preset management.');
molEditorIntegrationAssert(str_ends_with($bootstrap['api']['root'], '/wp-json/mol/v1/'), 'Editor REST root drifted.');
molEditorIntegrationAssert('' !== $bootstrap['api']['nonce'], 'Editor REST nonce is missing.');
molEditorIntegrationAssert(false === $bootstrap['chapter']['is_published'], 'Draft publication state drifted.');
molEditorIntegrationAssert(null === $bootstrap['links']['reader'], 'Draft chapter exposed a public reader link.');
molEditorIntegrationAssert(2 === count($bootstrap['pages']), 'Presented editor page count drifted.');

wp_set_current_user($memberId);
$capturedStatus = 0;
$dieHandler = static function () use (&$capturedStatus): callable {
    return static function (mixed $message, mixed $title = '', array $arguments = array()) use (&$capturedStatus): void {
        unset($message, $title);
        $capturedStatus = (int) ($arguments['response'] ?? 0);
        throw new RuntimeException('mol_editor_wp_die');
    };
};
add_filter('wp_die_handler', $dieHandler);
try {
    require MOL_PLUGIN_DIR . 'templates/editor.php';
    throw new RuntimeException('Member editor template did not stop execution.');
} catch (RuntimeException $error) {
    molEditorIntegrationAssert('mol_editor_wp_die' === $error->getMessage(), 'Unexpected member template failure.');
} finally {
    remove_filter('wp_die_handler', $dieHandler);
}
molEditorIntegrationAssert(403 === $capturedStatus, 'Member editor template did not produce a 403 response.');

wp_set_current_user($translatorId);
ob_start();
require MOL_PLUGIN_DIR . 'templates/editor.php';
$templateOutput = (string) ob_get_clean();
molEditorIntegrationAssert(str_contains($templateOutput, 'id="mol-editor-root"'), 'Editor root is missing.');
molEditorIntegrationAssert(str_contains($templateOutput, 'id="mol-editor-bootstrap"'), 'Editor JSON bootstrap is missing.');
molEditorIntegrationAssert(str_contains($templateOutput, 'editor.js'), 'Built editor script is missing from the template.');
molEditorIntegrationAssert(str_contains($templateOutput, '\\u003C'), 'Editor JSON did not hex-escape untrusted markup.');
molEditorIntegrationAssert(
    ! str_contains($templateOutput, '</script><img src=x'),
    'Untrusted element content escaped the JSON script boundary.'
);

wp_reset_postdata();
wp_set_current_user($administratorId);
echo "Manga Overlay editor-shell integration passed.\n";
