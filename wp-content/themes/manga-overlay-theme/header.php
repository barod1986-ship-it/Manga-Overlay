<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$libraryUrl = mol_theme_library_url();
?>
<!doctype html>
<html <?php echo mol_theme_language_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from Core attributes and a fixed dir. ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="mol-skip-link" href="#mol-content"><?php esc_html_e('تخطَّ إلى المحتوى', 'manga-overlay-theme'); ?></a>

<header class="mol-site-header">
    <div class="mol-shell mol-site-header__inner">
        <a class="mol-brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
            <span class="mol-brand__mark" aria-hidden="true"><b>M</b><i>O</i></span>
            <span class="mol-brand__copy">
                <strong dir="auto"><?php bloginfo('name'); ?></strong>
                <small><?php esc_html_e('الترجمة فوق الصورة، لا داخلها', 'manga-overlay-theme'); ?></small>
            </span>
        </a>

        <nav class="mol-primary-nav" aria-label="<?php esc_attr_e('التنقل الرئيسي', 'manga-overlay-theme'); ?>">
            <?php if (has_nav_menu('primary')) : ?>
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'container' => false,
                    'menu_class' => 'mol-primary-nav__list',
                    'fallback_cb' => false,
                    'depth' => 1,
                ));
                ?>
            <?php else : ?>
                <ul class="mol-primary-nav__list">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('الرئيسية', 'manga-overlay-theme'); ?></a></li>
                    <li><a href="<?php echo esc_url($libraryUrl); ?>"><?php esc_html_e('المكتبة', 'manga-overlay-theme'); ?></a></li>
                </ul>
            <?php endif; ?>
        </nav>

        <a class="mol-header-search" href="<?php echo esc_url($libraryUrl); ?>#mol-library-search">
            <?php echo mol_theme_icon('search'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            <span><?php esc_html_e('ابحث', 'manga-overlay-theme'); ?></span>
        </a>
    </div>
</header>

