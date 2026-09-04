<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$libraryQuery = mol_theme_current_library_query();
$library = mol_theme_library_data($libraryQuery);
$works = is_array($library['data'] ?? null) ? $library['data'] : array();
$meta = is_array($library['meta'] ?? null) ? $library['meta'] : array();
$filterOptions = mol_theme_filter_options();
$total = max(0, (int) ($meta['total'] ?? 0));
$currentPage = max(1, (int) ($meta['page'] ?? $libraryQuery['page']));
$totalPages = max(0, (int) ($meta['total_pages'] ?? 0));
$firstResult = $total > 0 ? (($currentPage - 1) * (int) $libraryQuery['per_page']) + 1 : 0;
$lastResult = min($total, $currentPage * (int) $libraryQuery['per_page']);
$activeFilterCount = count(array_filter(array(
    $libraryQuery['search'],
    $libraryQuery['type'],
    $libraryQuery['genre'],
    $libraryQuery['source_lang'],
    $libraryQuery['work_status'],
    $libraryQuery['translation_status'],
)));

get_header();
?>
<main id="mol-content" class="mol-library-main">
    <section class="mol-library-masthead">
        <div class="mol-shell mol-library-masthead__inner">
            <div>
                <span class="mol-kicker">MANGA · MANHWA · COMICS</span>
                <h1><?php esc_html_e('المكتبة', 'manga-overlay-theme'); ?></h1>
                <p><?php esc_html_e('ابحث عن العمل، ثم تابع حالة الترجمة العربية فصلًا بفصل.', 'manga-overlay-theme'); ?></p>
            </div>
            <div class="mol-library-masthead__count" aria-live="polite">
                <strong><?php echo esc_html(number_format_i18n($total)); ?></strong>
                <span><?php esc_html_e('عملًا منشورًا', 'manga-overlay-theme'); ?></span>
            </div>
        </div>
    </section>

    <div class="mol-shell mol-library-layout">
        <button class="mol-button mol-button--filter" type="button" data-mol-filter-toggle aria-controls="mol-library-filters" aria-expanded="false">
            <?php echo mol_theme_icon('filter'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            <span><?php esc_html_e('الفلاتر', 'manga-overlay-theme'); ?></span>
            <?php if ($activeFilterCount > 0) : ?>
                <b aria-label="<?php echo esc_attr(sprintf(__('عدد الفلاتر النشطة: %d', 'manga-overlay-theme'), $activeFilterCount)); ?>"><?php echo esc_html((string) $activeFilterCount); ?></b>
            <?php endif; ?>
        </button>

        <?php get_template_part('template-parts/library-filters', null, array('query' => $libraryQuery, 'options' => $filterOptions)); ?>

        <section class="mol-library-results" aria-labelledby="mol-library-results-heading">
            <header class="mol-results-header">
                <div>
                    <span class="mol-kicker"><?php esc_html_e('نتائج التصفح', 'manga-overlay-theme'); ?></span>
                    <h2 id="mol-library-results-heading">
                        <?php if ($total > 0) : ?>
                            <?php echo esc_html(sprintf(__('عرض %1$d–%2$d من %3$d', 'manga-overlay-theme'), $firstResult, $lastResult, $total)); ?>
                        <?php else : ?>
                            <?php esc_html_e('لا توجد نتائج', 'manga-overlay-theme'); ?>
                        <?php endif; ?>
                    </h2>
                </div>
                <span class="mol-results-header__sort">
                    <?php
                    $sortLabels = array(
                        'latest_chapter' => 'آخر فصل',
                        'latest_work' => 'أحدث عمل',
                        'title_asc' => 'العنوان أبجديًا',
                        'most_read' => 'الأكثر قراءة',
                    );
                    echo esc_html($sortLabels[(string) $libraryQuery['sort']] ?? 'آخر فصل');
                    ?>
                </span>
            </header>

            <?php if (is_array($library['error'] ?? null)) : ?>
                <div class="mol-notice mol-notice--error" role="alert">
                    <strong><?php esc_html_e('تعذر تحميل المكتبة', 'manga-overlay-theme'); ?></strong>
                    <p><?php echo esc_html((string) ($library['error']['message'] ?? 'تعذر تحميل البيانات المطلوبة.')); ?></p>
                    <?php if ('mol_sort_unavailable' === ($library['error']['code'] ?? '')) : ?>
                        <a href="<?php echo esc_url(mol_theme_library_url(array_merge($libraryQuery, array('sort' => 'latest_chapter', 'page' => 1)))); ?>"><?php esc_html_e('استخدم ترتيب آخر فصل', 'manga-overlay-theme'); ?></a>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($works)) : ?>
                <div class="mol-empty-state">
                    <span class="mol-empty-state__mark" aria-hidden="true">؟</span>
                    <h2><?php esc_html_e('لا شيء يطابق هذه الفلاتر', 'manga-overlay-theme'); ?></h2>
                    <p><?php esc_html_e('جرّب إزالة تصنيف أو البحث بكلمة أقصر.', 'manga-overlay-theme'); ?></p>
                    <a class="mol-button mol-button--primary" href="<?php echo esc_url(mol_theme_library_url()); ?>"><?php esc_html_e('عرض كل الأعمال', 'manga-overlay-theme'); ?></a>
                </div>
            <?php else : ?>
                <div class="mol-work-grid">
                    <?php foreach ($works as $index => $work) : ?>
                        <?php if (is_array($work)) : ?>
                            <?php get_template_part('template-parts/work-card', null, array('work' => $work, 'priority' => 0 === $index)); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php
                echo mol_theme_pagination_markup(
                    $currentPage,
                    $totalPages,
                    mol_theme_library_url(),
                    mol_theme_compact_library_query($libraryQuery, false),
                    'library_page'
                ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by helper.
                ?>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php
get_footer();
