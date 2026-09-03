<?php

// WP-CLI eval-file injects bootstrap code before this file, so a strict_types
// declaration cannot legally be the first evaluated statement here.

use MOL\Content\RewriteManager;
use MOL\Content\WorkContent;
use MOL\Content\WorkMeta;

function molContentIntegrationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

global $wpdb;
molContentIntegrationAssert($wpdb instanceof wpdb, 'WordPress did not expose wpdb.');
molContentIntegrationAssert(defined('ABSPATH'), 'WordPress was not loaded.');
molContentIntegrationAssert('7.1' === get_bloginfo('version'), 'Unexpected WordPress version.');

$postType = get_post_type_object(WorkContent::POST_TYPE);
molContentIntegrationAssert($postType instanceof WP_Post_Type, 'mol_work was not registered.');
molContentIntegrationAssert(true === $postType->public, 'mol_work is not public.');
molContentIntegrationAssert(true === $postType->show_in_rest, 'mol_work is not exposed through Core REST.');
molContentIntegrationAssert('library' === $postType->has_archive, 'mol_work archive slug changed.');
molContentIntegrationAssert('series' === $postType->rewrite['slug'], 'mol_work single slug changed.');
molContentIntegrationAssert(false === $postType->rewrite['with_front'], 'mol_work rewrite unexpectedly uses front.');
molContentIntegrationAssert(true === $postType->map_meta_cap, 'mol_work does not map meta capabilities.');

foreach (array('title', 'editor', 'thumbnail', 'custom-fields') as $feature) {
    molContentIntegrationAssert(
        post_type_supports(WorkContent::POST_TYPE, $feature),
        sprintf('mol_work is missing %s support.', $feature)
    );
}

foreach (array(
    'edit_posts',
    'edit_others_posts',
    'publish_posts',
    'read_private_posts',
    'delete_posts',
    'create_posts',
) as $capabilityProperty) {
    molContentIntegrationAssert(
        'mol_manage_content' === $postType->cap->{$capabilityProperty},
        sprintf('mol_work %s is not mapped to mol_manage_content.', $capabilityProperty)
    );
}

foreach (WorkContent::taxonomyNames() as $taxonomyName) {
    $taxonomy = get_taxonomy($taxonomyName);
    molContentIntegrationAssert($taxonomy instanceof WP_Taxonomy, sprintf('%s was not registered.', $taxonomyName));
    molContentIntegrationAssert(in_array(WorkContent::POST_TYPE, $taxonomy->object_type, true), sprintf('%s is detached.', $taxonomyName));
    molContentIntegrationAssert(true === $taxonomy->public, sprintf('%s is not public.', $taxonomyName));
    molContentIntegrationAssert(true === $taxonomy->show_in_rest, sprintf('%s is not exposed through REST.', $taxonomyName));
    foreach (array('manage_terms', 'edit_terms', 'delete_terms', 'assign_terms') as $capabilityProperty) {
        molContentIntegrationAssert(
            'mol_manage_content' === $taxonomy->cap->{$capabilityProperty},
            sprintf('%s %s is not mapped to mol_manage_content.', $taxonomyName, $capabilityProperty)
        );
    }
}

$workTypeSlugs = get_terms(array(
    'taxonomy' => WorkContent::WORK_TYPE_TAXONOMY,
    'hide_empty' => false,
    'fields' => 'slugs',
));
molContentIntegrationAssert(! is_wp_error($workTypeSlugs), 'Could not read canonical work-type terms.');
sort($workTypeSlugs);
$expectedWorkTypeSlugs = WorkContent::canonicalWorkTypeSlugs();
sort($expectedWorkTypeSlugs);
molContentIntegrationAssert($expectedWorkTypeSlugs === $workTypeSlugs, 'Canonical work-type terms drifted.');

$registeredMeta = get_registered_meta_keys('post', WorkContent::POST_TYPE);
foreach (array(WorkMeta::ALT_TITLES, WorkMeta::DEFAULT_READER_MODE, WorkMeta::READING_DIRECTION) as $metaKey) {
    molContentIntegrationAssert(isset($registeredMeta[$metaKey]), sprintf('%s was not registered.', $metaKey));
    molContentIntegrationAssert(true === $registeredMeta[$metaKey]['single'], sprintf('%s is not single-value meta.', $metaKey));
    molContentIntegrationAssert(is_callable($registeredMeta[$metaKey]['sanitize_callback']), sprintf('%s has no sanitizer.', $metaKey));
    molContentIntegrationAssert(is_callable($registeredMeta[$metaKey]['auth_callback']), sprintf('%s has no auth callback.', $metaKey));
    molContentIntegrationAssert(false !== $registeredMeta[$metaKey]['show_in_rest'], sprintf('%s is hidden from REST.', $metaKey));
}
molContentIntegrationAssert('array' === $registeredMeta[WorkMeta::ALT_TITLES]['type'], 'Alternative titles are not array meta.');
molContentIntegrationAssert(
    WorkMeta::readerModes() === $registeredMeta[WorkMeta::DEFAULT_READER_MODE]['show_in_rest']['schema']['enum'],
    'Reader-mode REST enum drifted.'
);
molContentIntegrationAssert(
    WorkMeta::readingDirections() === $registeredMeta[WorkMeta::READING_DIRECTION]['show_in_rest']['schema']['enum'],
    'Direction REST enum drifted.'
);

$administratorId = get_current_user_id();
molContentIntegrationAssert($administratorId > 0, 'The integration test is not authenticated.');
molContentIntegrationAssert(current_user_can('mol_manage_content'), 'The integration administrator cannot manage content.');

$workId = wp_insert_post(array(
    'post_type' => WorkContent::POST_TYPE,
    'post_status' => 'publish',
    'post_title' => 'T-05 Integration Work',
    'post_content' => 'Work description.',
), true);
molContentIntegrationAssert(! is_wp_error($workId), 'Could not create a mol_work post.');
$workId = (int) $workId;

update_post_meta($workId, WorkMeta::ALT_TITLES, array('  Alias One  ', '<b>Alias Two</b>', 'Alias One', ''));
update_post_meta($workId, WorkMeta::DEFAULT_READER_MODE, 'paged');
update_post_meta($workId, WorkMeta::READING_DIRECTION, 'ltr');
molContentIntegrationAssert(
    array('Alias One', 'Alias Two') === get_post_meta($workId, WorkMeta::ALT_TITLES, true),
    'Alternative-title sanitization failed in WordPress.'
);
molContentIntegrationAssert('paged' === get_post_meta($workId, WorkMeta::DEFAULT_READER_MODE, true), 'Reader mode did not persist.');
molContentIntegrationAssert('ltr' === get_post_meta($workId, WorkMeta::READING_DIRECTION, true), 'Reading direction did not persist.');

foreach (array(
    WorkContent::WORK_TYPE_TAXONOMY => 'manga',
    WorkContent::GENRE_TAXONOMY => 'fantasy',
    WorkContent::SOURCE_LANGUAGE_TAXONOMY => 'ja',
    WorkContent::WORK_STATUS_TAXONOMY => 'ongoing',
) as $taxonomyName => $termSlug) {
    $assigned = wp_set_object_terms($workId, array($termSlug), $taxonomyName);
    molContentIntegrationAssert(! is_wp_error($assigned), sprintf('Could not assign %s.', $taxonomyName));
}

$archiveUrl = get_post_type_archive_link(WorkContent::POST_TYPE);
molContentIntegrationAssert(home_url('/library/') === $archiveUrl, 'Library archive URL drifted.');
$workUrl = get_permalink($workId);
molContentIntegrationAssert(
    str_ends_with($workUrl, '/series/' . get_post_field('post_name', $workId) . '/'),
    'Work permalink does not use /series/{slug}/.'
);

$rewriteRules = get_option('rewrite_rules', array());
molContentIntegrationAssert(
    isset($rewriteRules['^series/([^/]+)/chapter/([^/]+)/edit/?$']),
    'Chapter editor rewrite rule is missing.'
);
molContentIntegrationAssert(
    isset($rewriteRules['^series/([^/]+)/chapter/([^/]+)/?$']),
    'Chapter reader rewrite rule is missing.'
);
molContentIntegrationAssert(isset($rewriteRules['^u/([^/]+)/?$']), 'User-profile rewrite rule is missing.');
$queryVariables = apply_filters('query_vars', array());
molContentIntegrationAssert(in_array('mol_chapter', $queryVariables, true), 'mol_chapter query var is missing.');
molContentIntegrationAssert(in_array('mol_editor', $queryVariables, true), 'mol_editor query var is missing.');
molContentIntegrationAssert(
    RewriteManager::VERSION === (string) get_option(RewriteManager::VERSION_OPTION),
    'Rewrite version was not stored.'
);
$rewriteAutoload = $wpdb->get_var($wpdb->prepare(
    "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
    RewriteManager::VERSION_OPTION
));
molContentIntegrationAssert(in_array($rewriteAutoload, array('no', 'off'), true), 'Rewrite version is autoloaded.');

wp_set_current_user(0);
$publicResponse = rest_do_request(new WP_REST_Request('GET', '/wp/v2/mol_work/' . $workId));
molContentIntegrationAssert(200 === $publicResponse->get_status(), 'Published work is not publicly readable through Core REST.');
$publicData = $publicResponse->get_data();
molContentIntegrationAssert(
    array('Alias One', 'Alias Two') === $publicData['meta'][WorkMeta::ALT_TITLES],
    'Public Core REST omitted or changed alternative titles.'
);
molContentIntegrationAssert('paged' === $publicData['meta'][WorkMeta::DEFAULT_READER_MODE], 'Public Core REST omitted reader mode.');
molContentIntegrationAssert('ltr' === $publicData['meta'][WorkMeta::READING_DIRECTION], 'Public Core REST omitted direction.');

$memberId = wp_create_user('mol_t05_member', wp_generate_password(32), 'mol-t05-member@example.invalid');
molContentIntegrationAssert(! is_wp_error($memberId), 'Could not create the unprivileged integration user.');
$member = get_user_by('id', $memberId);
molContentIntegrationAssert($member instanceof WP_User, 'Could not load the unprivileged integration user.');
$member->set_role('mol_member');
wp_set_current_user((int) $memberId);
molContentIntegrationAssert(! current_user_can('mol_manage_content'), 'The member unexpectedly manages content.');

$forbiddenCreate = new WP_REST_Request('POST', '/wp/v2/mol_work');
$forbiddenCreate->set_param('title', 'Forbidden Work');
$forbiddenCreate->set_param('status', 'draft');
$forbiddenResponse = rest_do_request($forbiddenCreate);
molContentIntegrationAssert(403 === $forbiddenResponse->get_status(), 'A member created mol_work through Core REST.');

wp_set_current_user($administratorId);
$invalidMetaRequest = new WP_REST_Request('POST', '/wp/v2/mol_work/' . $workId);
$invalidMetaRequest->set_param('meta', array(WorkMeta::DEFAULT_READER_MODE => 'scroll'));
$invalidMetaResponse = rest_do_request($invalidMetaRequest);
molContentIntegrationAssert(400 === $invalidMetaResponse->get_status(), 'Core REST accepted an invalid reader mode.');

$validMetaRequest = new WP_REST_Request('POST', '/wp/v2/mol_work/' . $workId);
$validMetaRequest->set_param('meta', array(
    WorkMeta::DEFAULT_READER_MODE => 'webtoon',
    WorkMeta::READING_DIRECTION => 'rtl',
));
$validMetaResponse = rest_do_request($validMetaRequest);
molContentIntegrationAssert(200 === $validMetaResponse->get_status(), 'Content manager could not update work meta.');

echo "Manga Overlay content integration passed.\n";
