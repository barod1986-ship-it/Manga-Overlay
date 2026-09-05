<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="mol-content" class="mol-shell mol-error-page">
    <span class="mol-error-page__code" aria-hidden="true">404</span>
    <span class="mol-kicker"><?php esc_html_e('هذه اللوحة مفقودة', 'manga-overlay-theme'); ?></span>
    <h1><?php esc_html_e('لم نجد الصفحة التي تبحث عنها', 'manga-overlay-theme'); ?></h1>
    <p><?php esc_html_e('قد يكون الرابط قديمًا، أو أن الفصل غير منشور للعامة.', 'manga-overlay-theme'); ?></p>
    <a class="mol-button mol-button--primary" href="<?php echo esc_url(mol_theme_library_url()); ?>"><?php esc_html_e('العودة إلى المكتبة', 'manga-overlay-theme'); ?></a>
</main>
<?php
get_footer();

