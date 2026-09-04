<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

function mol_theme_setup(): void
{
    load_theme_textdomain('manga-overlay-theme', MOL_THEME_DIRECTORY . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('custom-logo', array(
        'height' => 96,
        'width' => 96,
        'flex-height' => true,
        'flex-width' => true,
    ));
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));
    register_nav_menus(array(
        'primary' => __('التنقل الرئيسي', 'manga-overlay-theme'),
        'footer' => __('روابط التذييل', 'manga-overlay-theme'),
    ));
    add_image_size('mol-cover-card', 480, 720, true);

    $GLOBALS['content_width'] = 760;
}
add_action('after_setup_theme', 'mol_theme_setup');

function mol_theme_enqueue_assets(): void
{
    $stylePath = MOL_THEME_DIRECTORY . '/assets/css/main.css';
    $scriptPath = MOL_THEME_DIRECTORY . '/assets/js/theme.js';
    wp_enqueue_style(
        'manga-overlay-theme',
        MOL_THEME_URI . '/assets/css/main.css',
        array(),
        is_file($stylePath) ? (string) filemtime($stylePath) : MOL_THEME_VERSION
    );
    wp_enqueue_script(
        'manga-overlay-theme',
        MOL_THEME_URI . '/assets/js/theme.js',
        array(),
        is_file($scriptPath) ? (string) filemtime($scriptPath) : MOL_THEME_VERSION,
        array('in_footer' => false)
    );
    wp_script_add_data('manga-overlay-theme', 'strategy', 'defer');
}
add_action('wp_enqueue_scripts', 'mol_theme_enqueue_assets');

/** @param list<string> $classes @return list<string> */
function mol_theme_body_classes(array $classes): array
{
    $classes[] = 'mol-ui';
    if (is_post_type_archive('mol_work')) {
        $classes[] = 'mol-library-page';
    }
    if (is_singular('mol_work')) {
        $classes[] = 'mol-work-page';
    }

    return array_values(array_unique($classes));
}
add_filter('body_class', 'mol_theme_body_classes');

