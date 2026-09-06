<?php
defined('ABSPATH') || exit;
$mol_editor_context = MOL\Frontend\EditorSite::$context;
if (!$mol_editor_context || !current_user_can('mol_use_editor')) {
	wp_die(esc_html('لا تملك صلاحية دخول محرر الترجمة.'), '', ['response' => 403]);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('mol-editing'); ?>><?php wp_body_open(); ?>
<div id="mol-editor-root"><p role="status">جارٍ فتح مساحة الترجمة…</p></div>
<noscript><p>فعّل JavaScript لعرض مساحة الترجمة.</p><a href="<?php echo esc_url($mol_editor_context['backUrl']); ?>"><?php echo esc_html($mol_editor_context['backLabel']); ?></a></noscript>
<script type="application/json" id="mol-editor-data"><?php echo wp_json_encode($mol_editor_context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php wp_footer(); ?></body></html>
