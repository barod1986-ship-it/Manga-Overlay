<?php
if (!class_exists('MOL\\Frontend\\PublicSite')) { require __DIR__ . '/index.php'; return; }
try { $work = MOL\Frontend\PublicSite::work(get_queried_object_id()); } catch (MOL\Domain\Fault $error) { status_header(404); require __DIR__ . '/404.php'; return; }
$chapters = mol_get_work_chapters($work['id']);
get_header();
?>
<main id="mol-main" class="mol-site-main">
<a class="mol-back" href="<?php echo esc_url(home_url('/library/')); ?>">المكتبة</a>
<article class="mol-work-detail"><?php mol_theme_cover($work, true); ?><div><p class="mol-kicker"><?php echo esc_html(MOL\Content\WorkType::TYPES[$work['type']] ?? $work['type']); ?></p><h1 dir="auto"><?php echo esc_html($work['title']); ?></h1>
<?php if ($work['alt_titles']) : ?><ul class="mol-alt-titles"><?php foreach ($work['alt_titles'] as $title) : ?><li dir="auto"><?php echo esc_html($title); ?></li><?php endforeach; ?></ul><?php endif; ?>
<dl class="mol-metadata"><?php foreach (MOL\Content\WorkType::TAXONOMIES as $taxonomy => $label) : $terms = get_the_terms($work['id'], $taxonomy); if (!$terms || is_wp_error($terms)) { continue; } ?><div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html(implode('، ', wp_list_pluck($terms, 'name'))); ?></dd></div><?php endforeach; ?><div><dt>الفصول المنشورة</dt><dd><?php echo count($chapters); ?></dd></div></dl>
<div class="mol-description"><?php echo wp_kses_post(wpautop($work['description'])); ?></div>
<?php if ($chapters) : ?><a class="mol-button" href="<?php echo esc_url(mol_theme_chapter_link($chapters[0])); ?>">ابدأ القراءة</a><?php endif; ?></div></article>
<section><div class="mol-section-heading"><h2>الفصول</h2><span><?php echo count($chapters); ?> فصل</span></div>
<?php if (!$chapters) : ?><p class="mol-empty">لم تُنشر فصول لهذا العمل بعد.</p><?php else : ?><ol class="mol-chapter-list"><?php foreach ($chapters as $chapter) : ?><li><a href="<?php echo esc_url(mol_theme_chapter_link($chapter)); ?>"><span>الفصل <bdi><?php echo esc_html($chapter['chapter_label']); ?></bdi></span><strong dir="auto"><?php echo esc_html($chapter['title'] ?? ''); ?></strong><small class="mol-status mol-status-<?php echo esc_attr($chapter['translation_status']); ?>"><?php echo esc_html(mol_theme_status($chapter['translation_status'])); ?></small></a></li><?php endforeach; ?></ol><?php endif; ?>
</section></main><?php get_footer(); ?>
