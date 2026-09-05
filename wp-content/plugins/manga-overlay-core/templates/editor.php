<?php

declare(strict_types=1);

use MOL\Frontend\EditorPage;
use MOL\REST\ApiException;

if (! defined('ABSPATH')) {
    exit;
}

if (! is_user_logged_in()) {
    auth_redirect();
    exit;
}

if (! EditorPage::currentUserCanEdit()) {
    wp_die(
        esc_html__('لا تملك صلاحية استخدام محرر الترجمة.', 'manga-overlay-core'),
        esc_html__('الوصول مرفوض', 'manga-overlay-core'),
        array('response' => 403, 'back_link' => true)
    );
}

try {
    $molEditorBootstrap = EditorPage::bootstrap();
} catch (ApiException $error) {
    $status = max(400, min(599, $error->status()));
    wp_die(
        404 === $status
            ? esc_html__('الفصل المطلوب غير موجود.', 'manga-overlay-core')
            : esc_html__('لا تملك صلاحية استخدام محرر الترجمة.', 'manga-overlay-core'),
        404 === $status
            ? esc_html__('الفصل غير موجود', 'manga-overlay-core')
            : esc_html__('الوصول مرفوض', 'manga-overlay-core'),
        array('response' => $status, 'back_link' => true)
    );
} catch (Throwable $error) {
    error_log(sprintf('Manga Overlay editor bootstrap failed: %s', $error->getMessage()));
    wp_die(
        esc_html__('تعذر تجهيز محرر الترجمة الآن.', 'manga-overlay-core'),
        esc_html__('خطأ في المحرر', 'manga-overlay-core'),
        array('response' => 500, 'back_link' => true)
    );
}

$molEditorJson = wp_json_encode(
    $molEditorBootstrap,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if (! is_string($molEditorJson)) {
    wp_die(
        esc_html__('تعذر تجهيز بيانات المحرر.', 'manga-overlay-core'),
        esc_html__('خطأ في المحرر', 'manga-overlay-core'),
        array('response' => 500, 'back_link' => true)
    );
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('mol-editor-document'); ?>>
<?php wp_body_open(); ?>
<a class="mol-editor-skip-link" href="#mol-editor-root"><?php esc_html_e('تجاوز إلى المحرر', 'manga-overlay-core'); ?></a>
<div id="mol-editor-root" dir="rtl" aria-live="polite">
    <noscript><?php esc_html_e('يتطلب محرر الترجمة تفعيل JavaScript.', 'manga-overlay-core'); ?></noscript>
</div>
<script id="mol-editor-bootstrap" type="application/json"><?php echo $molEditorJson; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safely encoded with JSON_HEX flags. ?></script>
<?php wp_footer(); ?>
</body>
</html>
