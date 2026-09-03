<?php

declare(strict_types=1);

namespace MOL\Content;

use RuntimeException;

final class WorkContent
{
    public const POST_TYPE = 'mol_work';
    public const GENRE_TAXONOMY = 'mol_genre';
    public const WORK_TYPE_TAXONOMY = 'mol_work_type';
    public const SOURCE_LANGUAGE_TAXONOMY = 'mol_source_language';
    public const WORK_STATUS_TAXONOMY = 'mol_work_status';

    /** @var array<string, string> */
    private const CANONICAL_WORK_TYPES = array(
        'manga' => 'Manga',
        'manhwa' => 'Manhwa',
        'manhua' => 'Manhua',
        'comic' => 'Comic',
        'webtoon' => 'Webtoon',
        'other' => 'Other',
    );

    public function registerHooks(): void
    {
        add_action('init', array($this, 'register'), 5);
    }

    public function register(): void
    {
        $this->registerPostType();
        $this->registerTaxonomies();
        $this->registerMeta();
    }

    public function synchronizeCanonicalTerms(): void
    {
        foreach (self::CANONICAL_WORK_TYPES as $slug => $name) {
            $existing = term_exists($slug, self::WORK_TYPE_TAXONOMY);
            if (null !== $existing && false !== $existing) {
                continue;
            }

            $created = wp_insert_term(
                $name,
                self::WORK_TYPE_TAXONOMY,
                array('slug' => $slug)
            );
            if (is_wp_error($created)) {
                throw new RuntimeException($created->get_error_message());
            }
        }
    }

    /** @return list<string> */
    public static function taxonomyNames(): array
    {
        return array(
            self::GENRE_TAXONOMY,
            self::WORK_TYPE_TAXONOMY,
            self::SOURCE_LANGUAGE_TAXONOMY,
            self::WORK_STATUS_TAXONOMY,
        );
    }

    /** @return list<string> */
    public static function canonicalWorkTypeSlugs(): array
    {
        return array_keys(self::CANONICAL_WORK_TYPES);
    }

    private function registerPostType(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name' => __('Works', 'manga-overlay-core'),
                    'singular_name' => __('Work', 'manga-overlay-core'),
                    'menu_name' => __('Manga Overlay', 'manga-overlay-core'),
                    'add_new_item' => __('Add Work', 'manga-overlay-core'),
                    'edit_item' => __('Edit Work', 'manga-overlay-core'),
                    'view_item' => __('View Work', 'manga-overlay-core'),
                    'search_items' => __('Search Works', 'manga-overlay-core'),
                    'not_found' => __('No works found.', 'manga-overlay-core'),
                ),
                'public' => true,
                'show_in_rest' => true,
                'has_archive' => 'library',
                'rewrite' => array(
                    'slug' => 'series',
                    'with_front' => false,
                ),
                'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
                'taxonomies' => self::taxonomyNames(),
                'map_meta_cap' => true,
                'capability_type' => array('mol_work', 'mol_works'),
                'capabilities' => self::postTypeCapabilities(),
                'menu_icon' => 'dashicons-book-alt',
            )
        );
    }

    private function registerTaxonomies(): void
    {
        foreach (self::taxonomyNames() as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                continue;
            }

            register_taxonomy(
                $taxonomy,
                array(self::POST_TYPE),
                array(
                    'labels' => array(
                        'name' => $this->taxonomyLabel($taxonomy),
                        'singular_name' => $this->taxonomySingularLabel($taxonomy),
                    ),
                    'public' => true,
                    'show_ui' => true,
                    'show_in_rest' => true,
                    'show_admin_column' => true,
                    'hierarchical' => false,
                    'rewrite' => false,
                    'capabilities' => array(
                        'manage_terms' => 'mol_manage_content',
                        'edit_terms' => 'mol_manage_content',
                        'delete_terms' => 'mol_manage_content',
                        'assign_terms' => 'mol_manage_content',
                    ),
                )
            );
        }
    }

    private function registerMeta(): void
    {
        register_post_meta(
            self::POST_TYPE,
            WorkMeta::ALT_TITLES,
            array(
                'type' => 'array',
                'single' => true,
                'default' => array(),
                'sanitize_callback' => array(WorkMeta::class, 'sanitizeAltTitles'),
                'auth_callback' => array(WorkMeta::class, 'authorizeMutation'),
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'array',
                        'items' => array('type' => 'string'),
                        'default' => array(),
                    ),
                ),
            )
        );

        register_post_meta(
            self::POST_TYPE,
            WorkMeta::DEFAULT_READER_MODE,
            array(
                'type' => 'string',
                'single' => true,
                'default' => WorkMeta::DEFAULT_READER_MODE_VALUE,
                'sanitize_callback' => array(WorkMeta::class, 'sanitizeReaderMode'),
                'auth_callback' => array(WorkMeta::class, 'authorizeMutation'),
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'string',
                        'enum' => WorkMeta::readerModes(),
                        'default' => WorkMeta::DEFAULT_READER_MODE_VALUE,
                    ),
                ),
            )
        );

        register_post_meta(
            self::POST_TYPE,
            WorkMeta::READING_DIRECTION,
            array(
                'type' => 'string',
                'single' => true,
                'default' => WorkMeta::DEFAULT_READING_DIRECTION_VALUE,
                'sanitize_callback' => array(WorkMeta::class, 'sanitizeReadingDirection'),
                'auth_callback' => array(WorkMeta::class, 'authorizeMutation'),
                'show_in_rest' => array(
                    'schema' => array(
                        'type' => 'string',
                        'enum' => WorkMeta::readingDirections(),
                        'default' => WorkMeta::DEFAULT_READING_DIRECTION_VALUE,
                    ),
                ),
            )
        );
    }

    /** @return array<string, string> */
    private static function postTypeCapabilities(): array
    {
        return array(
            'edit_post' => 'edit_mol_work',
            'read_post' => 'read_mol_work',
            'delete_post' => 'delete_mol_work',
            'edit_posts' => 'mol_manage_content',
            'edit_others_posts' => 'mol_manage_content',
            'publish_posts' => 'mol_manage_content',
            'read_private_posts' => 'mol_manage_content',
            'delete_posts' => 'mol_manage_content',
            'delete_private_posts' => 'mol_manage_content',
            'delete_published_posts' => 'mol_manage_content',
            'delete_others_posts' => 'mol_manage_content',
            'edit_private_posts' => 'mol_manage_content',
            'edit_published_posts' => 'mol_manage_content',
            'create_posts' => 'mol_manage_content',
        );
    }

    private function taxonomyLabel(string $taxonomy): string
    {
        return match ($taxonomy) {
            self::GENRE_TAXONOMY => __('Genres', 'manga-overlay-core'),
            self::WORK_TYPE_TAXONOMY => __('Work Types', 'manga-overlay-core'),
            self::SOURCE_LANGUAGE_TAXONOMY => __('Source Languages', 'manga-overlay-core'),
            self::WORK_STATUS_TAXONOMY => __('Work Statuses', 'manga-overlay-core'),
            default => $taxonomy,
        };
    }

    private function taxonomySingularLabel(string $taxonomy): string
    {
        return match ($taxonomy) {
            self::GENRE_TAXONOMY => __('Genre', 'manga-overlay-core'),
            self::WORK_TYPE_TAXONOMY => __('Work Type', 'manga-overlay-core'),
            self::SOURCE_LANGUAGE_TAXONOMY => __('Source Language', 'manga-overlay-core'),
            self::WORK_STATUS_TAXONOMY => __('Work Status', 'manga-overlay-core'),
            default => $taxonomy,
        };
    }
}
