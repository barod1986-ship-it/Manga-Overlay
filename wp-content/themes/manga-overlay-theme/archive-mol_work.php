<?php
if (!class_exists('MOL\\Frontend\\PublicSite')) { require __DIR__ . '/index.php'; return; }
$query = wp_unslash($_GET);
$query['page'] = $query['page'] ?? 1;
// Empty select values mean no filter, rather than an invalid enum value.
$query = array_filter($query, static fn ($value) => $value !== '');
$error = null;
try { $result = MOL\Frontend\PublicSite::library($query); } catch (MOL\Domain\Fault $fault) { $error = $fault->getMessage(); status_header(400); $result = ['data' => [], 'meta' => ['total' => 0, 'page' => 1, 'total_pages' => 0]]; }
get_header();
?>
<main id="mol-main" class="mol-site-main">
<div class="mol-section-heading"><div><p class="mol-kicker">مانجا · مانهوا · قصص مصورة</p><h1>المكتبة</h1></div><p><?php echo (int) $result['meta']['total']; ?> عمل</p></div>
<details class="mol-library-filters" open><summary>البحث وتصفية الأعمال</summary>
<form action="<?php echo esc_url(home_url('/library/')); ?>" method="get" class="mol-filter-form">
<label class="mol-filter-search">ابحث بالعنوان أو الاسم البديل<input type="search" name="search" maxlength="200" value="<?php echo esc_attr(is_string($query['search'] ?? null) ? $query['search'] : ''); ?>"></label>
<?php foreach (['type' => 'نوع العمل', 'genre' => 'التصنيف', 'source_lang' => 'لغة المصدر', 'work_status' => 'حالة العمل'] as $key => $label) : $taxonomy = ['type' => 'mol_work_type', 'genre' => 'mol_genre', 'source_lang' => 'mol_source_language', 'work_status' => 'mol_work_status'][$key]; $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]); ?>
<label><?php echo esc_html($label); ?><select name="<?php echo esc_attr($key === 'genre' ? 'genre[]' : $key); ?>" <?php if ($key === 'genre') : ?>multiple size="3"<?php endif; ?>><?php if ($key !== 'genre') : ?><option value="">الكل</option><?php endif; ?>
<?php if (!is_wp_error($terms)) : foreach ($terms as $term) : ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected(in_array($term->slug, (array) ($query[$key] ?? []), true)); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; endif; ?></select></label>
<?php endforeach; ?>
<label>حالة الترجمة<select name="translation_status"><option value="">الكل</option><?php foreach (['untranslated', 'in_progress', 'needs_review', 'completed'] as $status) : ?><option value="<?php echo esc_attr($status); ?>" <?php selected($query['translation_status'] ?? '', $status); ?>><?php echo esc_html(mol_theme_status($status)); ?></option><?php endforeach; ?></select></label>
<label>الترتيب<select name="sort"><?php foreach (['latest_chapter' => 'أحدث فصل', 'latest_work' => 'أحدث عمل', 'title_asc' => 'العنوان'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($query['sort'] ?? 'latest_chapter', $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
<button type="submit">تطبيق</button><a href="<?php echo esc_url(home_url('/library/')); ?>">مسح الفلاتر</a>
</form></details>
<?php if ($error) : ?><p role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
<?php get_template_part('template-parts/work-grid', null, ['works' => $result['data']]); ?>
<nav class="mol-pagination" aria-label="صفحات المكتبة"><?php $page = (int) $result['meta']['page']; unset($query['page'], $query['paged']); if ($page > 1) : ?><a href="<?php echo esc_url(add_query_arg(array_merge($query, ['page' => $page - 1]), home_url('/library/'))); ?>">السابق</a><?php endif; ?><span>صفحة <?php echo $page; ?> من <?php echo max(1, (int) $result['meta']['total_pages']); ?></span><?php if ($page < $result['meta']['total_pages']) : ?><a href="<?php echo esc_url(add_query_arg(array_merge($query, ['page' => $page + 1]), home_url('/library/'))); ?>">التالي</a><?php endif; ?></nav>
</main><?php get_footer(); ?>
