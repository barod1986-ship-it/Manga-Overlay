<?php
$chapter = MOL\Frontend\Routes::$chapter;
$work = MOL\Frontend\PublicSite::work($chapter['work_id']);
$pages = mol_get_chapter_pages($chapter['id']);
$chapters = mol_get_work_chapters($work['id']);
$contributors = mol_get_chapter_contributors($chapter['id']);
global $wpdb;
$progress = is_user_logged_in() ? (new MOL\Database\ProgressRepository($wpdb))->find(get_current_user_id(), $chapter['id']) : null;
$boot = ['work' => $work, 'chapter' => $chapter, 'pages' => $pages, 'overlays' => mol_get_chapter_elements($chapter['id']), 'progress' => $progress, 'api' => rest_url('mol/v1/'), 'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : null];
$position = array_search($chapter['id'], array_column($chapters, 'id'), true);
get_header();
?>
<main id="mol-main" class="mol-reader" dir="rtl">
<header class="mol-reader-heading"><a href="<?php echo esc_url(mol_theme_work_url($work)); ?>" dir="auto"><?php echo esc_html($work['title']); ?></a><h1>الفصل <?php echo esc_html($chapter['chapter_label']); ?><?php if ($chapter['title']) : ?> · <bdi><?php echo esc_html($chapter['title']); ?></bdi><?php endif; ?></h1><p><?php echo esc_html(mol_theme_status($chapter['translation_status'])); ?></p></header>
<button type="button" id="mol-show-toolbar" class="mol-show-toolbar" hidden>إظهار أدوات القراءة</button>
<div class="mol-reader-toolbar" id="mol-reader-toolbar">
<label>الفصل<select id="mol-reader-chapter"><?php foreach ($chapters as $item) : ?><option value="<?php echo esc_url(mol_theme_chapter_link($item)); ?>" <?php selected($item['id'], $chapter['id']); ?>><?php echo esc_html('الفصل ' . $item['chapter_label'] . ($item['title'] ? ' · ' . $item['title'] : '')); ?></option><?php endforeach; ?></select></label>
<label>الوضع<select id="mol-reader-mode"><option value="webtoon">تمرير عمودي</option><option value="paged" <?php selected($chapter['reader_mode_override'] ?? $work['default_reader_mode'], 'paged'); ?>>صفحات</option></select></label>
<button type="button" id="mol-reader-toggle" aria-pressed="true" disabled>الترجمة العربية</button>
<div class="mol-reader-zoom"><button type="button" id="mol-zoom-out" aria-label="تصغير الصورة" disabled>−</button><button type="button" id="mol-zoom-reset" aria-label="إعادة التكبير" disabled>100%</button><button type="button" id="mol-zoom-in" aria-label="تكبير الصورة" disabled>+</button></div>
<button type="button" id="mol-hide-toolbar" aria-label="إخفاء أدوات القراءة">إخفاء الأدوات</button>
</div>
<div class="mol-page-navigation" id="mol-page-navigation" hidden><button type="button" id="mol-page-previous">الصفحة السابقة</button><label>الصفحة<select id="mol-reader-page-select"><?php foreach ($pages as $page) : ?><option value="<?php echo (int) $page['page_index']; ?>"><?php echo (int) $page['page_index'] + 1; ?></option><?php endforeach; ?></select></label><button type="button" id="mol-page-next">الصفحة التالية</button></div>
<p class="mol-reader-feedback" role="status" id="mol-reader-status"></p>
<?php if (!$pages) : ?><p class="mol-empty">لم تُرفع صفحات لهذا الفصل بعد.</p><?php endif; ?>
<div class="mol-reader-viewport" id="mol-reader-viewport"><div class="mol-reader-track" id="mol-reader-track">
<?php foreach ($pages as $index => $page) : ?>
<figure class="mol-reader-page" data-page-index="<?php echo (int) $page['page_index']; ?>" data-page-id="<?php echo (int) $page['id']; ?>" style="aspect-ratio:<?php echo (int) $page['natural_width']; ?>/<?php echo (int) $page['natural_height']; ?>">
<img src="<?php echo esc_url($page['image']['url']); ?>" alt="<?php echo esc_attr($page['image']['alt'] ?: 'صفحة ' . ($index + 1)); ?>" width="<?php echo (int) $page['natural_width']; ?>" height="<?php echo (int) $page['natural_height']; ?>" loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>" <?php if ($index === 0) : ?>fetchpriority="high"<?php endif; ?> decoding="async" <?php if ($page['image']['srcset']) : ?>srcset="<?php echo esc_attr($page['image']['srcset']); ?>" sizes="(max-width: 860px) 100vw, 860px"<?php endif; ?>>
<div class="mol-reader-overlay"></div></figure>
<?php endforeach; ?></div></div>
<noscript><p class="mol-reader-feedback">يمكنك قراءة الصفحات الأصلية. فعّل JavaScript لعرض الترجمة وحفظ تقدم القراءة.</p></noscript>
<nav class="mol-chapter-navigation" aria-label="التنقل بين الفصول"><?php if ($position !== false && $position > 0) : ?><a href="<?php echo esc_url(mol_theme_chapter_link($chapters[$position - 1])); ?>">الفصل السابق</a><?php endif; ?><a href="<?php echo esc_url(mol_theme_work_url($work)); ?>">قائمة الفصول</a><?php if ($position !== false && $position + 1 < count($chapters)) : ?><a href="<?php echo esc_url(mol_theme_chapter_link($chapters[$position + 1])); ?>">الفصل التالي</a><?php endif; ?></nav>
<section class="mol-contributors"><h2>ساهموا في ترجمة الفصل</h2><?php if (!$contributors) : ?><p>لا توجد مساهمات ترجمة بعد.</p><?php else : ?><ul><?php foreach ($contributors as $person) : ?><li><?php if ($person['avatar_url']) : ?><img src="<?php echo esc_url($person['avatar_url']); ?>" alt="" width="36" height="36" loading="lazy"><?php endif; ?><div><?php if ($person['username']) : ?><a href="<?php echo esc_url(home_url('/u/' . $person['username'] . '/')); ?>" dir="auto"><?php echo esc_html($person['display_name']); ?></a><?php else : ?><span><?php echo esc_html($person['display_name']); ?></span><?php endif; ?><small><?php echo (int) $person['element_count']; ?> عنصر</small></div></li><?php endforeach; ?></ul><?php endif; ?></section>
<script type="application/json" id="mol-reader-data"><?php echo wp_json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
</main><?php get_footer(); ?>
