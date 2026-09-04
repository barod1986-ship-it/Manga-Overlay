<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html <?php echo mol_theme_language_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from Core attributes and a fixed UI direction. ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="mol-skip-link" href="#mol-reader-content"><?php esc_html_e('تخطَّ إلى صفحات الفصل', 'manga-overlay-theme'); ?></a>
