<?php
if (!class_exists('MOL\\Frontend\\PublicSite')) { require __DIR__ . '/index.php'; return; }
$recent = MOL\Frontend\PublicSite::library(['sort' => 'latest_chapter', 'per_page' => 8]);
$new = MOL\Frontend\PublicSite::library(['sort' => 'latest_work', 'per_page' => 8]);
get_header();
?>
<main id="mol-main" class="mol-site-main">
<div class="mol-section-heading"><div><p class="mol-kicker">المكتبة</p><h1>اختر قصة. ابدأ القراءة.</h1></div><form class="mol-search" action="<?php echo esc_url(home_url('/library/')); ?>" method="get"><label for="mol-home-search">ابحث عن عمل</label><div><input id="mol-home-search" type="search" name="search" placeholder="العنوان أو الاسم البديل" maxlength="200"><button type="submit">بحث</button></div></form></div>
<section><div class="mol-section-heading"><h2>أحدث الفصول</h2><a href="<?php echo esc_url(home_url('/library/')); ?>">كل الأعمال</a></div>
<div class="mol-recent-chapters"><?php $found = false; foreach ($recent['data'] as $work) : $chapters = mol_get_work_chapters($work['id']); usort($chapters, static fn ($a, $b) => strcmp($b['published_at'] ?? '', $a['published_at'] ?? '') ?: $b['id'] <=> $a['id']); if (!$chapters) { continue; } $chapter = $chapters[0]; $found = true; ?>
<a class="mol-latest-row" href="<?php echo esc_url(mol_theme_chapter_link($chapter)); ?>"><?php mol_theme_cover($work); ?><div><strong dir="auto"><?php echo esc_html($work['title']); ?></strong><span>الفصل <?php echo esc_html($chapter['chapter_label']); ?><?php if ($chapter['title']) : ?> · <bdi><?php echo esc_html($chapter['title']); ?></bdi><?php endif; ?></span></div><small><?php echo esc_html(mol_theme_status($chapter['translation_status'])); ?></small></a>
<?php endforeach; if (!$found) : ?><p class="mol-empty">لم تُنشر فصول بعد.</p><?php endif; ?></div></section>
<section><div class="mol-section-heading"><h2>أعمال أُضيفت حديثًا</h2></div><?php get_template_part('template-parts/work-grid', null, ['works' => $new['data']]); ?></section>
</main><?php get_footer(); ?>
