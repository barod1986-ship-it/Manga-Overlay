<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$chapterSlug = (string) get_query_var('mol_chapter');
if ('' !== $chapterSlug) {
    require MOL_THEME_DIRECTORY . '/chapter-reader.php';
    return;
}

$workId = get_queried_object_id();
$workResult = mol_theme_work_data($workId);
$work = is_array($workResult['data'] ?? null) ? $workResult['data'] : array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public pagination.
$chapterPageSource = isset($_GET['chapter_page']) ? wp_unslash($_GET['chapter_page']) : 1;
$chapterPage = mol_theme_query_positive_integer($chapterPageSource, 1, 100000);
$chaptersResult = mol_theme_work_chapters_data($workId, $chapterPage, 50);
$chapters = is_array($chaptersResult['data'] ?? null) ? $chaptersResult['data'] : array();
$chapterMeta = is_array($chaptersResult['meta'] ?? null) ? $chaptersResult['meta'] : array();
$title = is_string($work['title'] ?? null) ? $work['title'] : get_the_title($workId);
$cover = is_array($work['cover'] ?? null) ? $work['cover'] : array();
$genres = is_array($work['genres'] ?? null) ? $work['genres'] : array();
$altTitles = is_array($work['alt_titles'] ?? null) ? $work['alt_titles'] : array();
$summary = is_array($work['translation_summary'] ?? null) ? $work['translation_summary'] : array();
$totalChapters = max(0, (int) ($chapterMeta['total'] ?? 0));
$chapterTotalPages = max(0, (int) ($chapterMeta['total_pages'] ?? 0));

get_header();
?>
<main id="mol-content" class="mol-work-main">
    <?php if (is_array($workResult['error'] ?? null)) : ?>
        <div class="mol-shell mol-work-load-error">
            <div class="mol-notice mol-notice--error" role="alert">
                <strong><?php esc_html_e('تعذر تحميل بيانات العمل', 'manga-overlay-theme'); ?></strong>
                <p><?php echo esc_html((string) ($workResult['error']['message'] ?? 'تعذر تحميل البيانات المطلوبة.')); ?></p>
            </div>
        </div>
    <?php else : ?>
        <article>
            <header class="mol-work-hero">
                <div class="mol-shell mol-work-hero__grid">
                    <div class="mol-work-hero__cover">
                        <?php echo mol_theme_cover_markup($cover, true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by helper. ?>
                    </div>
                    <div class="mol-work-hero__content">
                        <div class="mol-work-hero__trail">
                            <a href="<?php echo esc_url(mol_theme_library_url()); ?>"><?php esc_html_e('المكتبة', 'manga-overlay-theme'); ?></a>
                            <span aria-hidden="true">/</span>
                            <span><?php echo esc_html(mol_theme_work_type_label((string) ($work['type'] ?? 'other'))); ?></span>
                        </div>
                        <h1 dir="auto"><?php echo esc_html($title); ?></h1>
                        <?php if (! empty($altTitles)) : ?>
                            <p class="mol-work-hero__alt-titles" dir="auto"><?php echo esc_html(implode(' · ', array_filter($altTitles, 'is_string'))); ?></p>
                        <?php endif; ?>

                        <div class="mol-work-tags" aria-label="<?php esc_attr_e('بيانات العمل', 'manga-overlay-theme'); ?>">
                            <span class="mol-chip mol-chip--dark"><?php echo esc_html(mol_theme_work_type_label((string) ($work['type'] ?? 'other'))); ?></span>
                            <?php if (! empty($work['work_status'])) : ?>
                                <span class="mol-chip"><?php echo esc_html(mol_theme_taxonomy_label('mol_work_status', (string) $work['work_status'])); ?></span>
                            <?php endif; ?>
                            <?php if (! empty($work['source_language'])) : ?>
                                <span class="mol-chip" dir="auto"><?php echo esc_html(mol_theme_taxonomy_label('mol_source_language', (string) $work['source_language'])); ?></span>
                            <?php endif; ?>
                        </div>

                        <dl class="mol-work-facts">
                            <div>
                                <dt><?php esc_html_e('نمط القراءة', 'manga-overlay-theme'); ?></dt>
                                <dd><?php echo mol_theme_icon((string) ($work['default_reader_mode'] ?? 'webtoon')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?> <?php echo esc_html(mol_theme_reader_mode_label((string) ($work['default_reader_mode'] ?? 'webtoon'))); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('الاتجاه', 'manga-overlay-theme'); ?></dt>
                                <dd><?php echo esc_html(mol_theme_direction_label((string) ($work['reading_direction'] ?? 'rtl'))); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('الفصول المنشورة', 'manga-overlay-theme'); ?></dt>
                                <dd><?php echo esc_html(number_format_i18n((int) ($summary['total'] ?? 0))); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('الترجمة المكتملة', 'manga-overlay-theme'); ?></dt>
                                <dd><?php echo esc_html((string) mol_theme_translation_percent($summary)); ?>%</dd>
                            </div>
                        </dl>

                        <?php if (! empty($genres)) : ?>
                            <div class="mol-work-genres" aria-label="<?php esc_attr_e('التصنيفات', 'manga-overlay-theme'); ?>">
                                <?php foreach ($genres as $genre) : ?>
                                    <?php if (is_string($genre)) : ?>
                                        <a href="<?php echo esc_url(mol_theme_library_url(array('genre' => array($genre)))); ?>" dir="auto"><?php echo esc_html(mol_theme_taxonomy_label('mol_genre', $genre)); ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="mol-shell mol-work-body">
                <section class="mol-work-description" aria-labelledby="mol-work-description-heading">
                    <span class="mol-kicker"><?php esc_html_e('عن العمل', 'manga-overlay-theme'); ?></span>
                    <h2 id="mol-work-description-heading"><?php esc_html_e('الوصف', 'manga-overlay-theme'); ?></h2>
                    <div class="mol-prose">
                        <?php echo wp_kses_post((string) ($work['description'] ?? '')); ?>
                    </div>
                </section>

                <section class="mol-chapters" id="mol-chapter-list" aria-labelledby="mol-chapters-heading">
                    <header class="mol-section-heading mol-chapters__heading">
                        <div>
                            <span class="mol-kicker"><?php esc_html_e('ابدأ القراءة', 'manga-overlay-theme'); ?></span>
                            <h2 id="mol-chapters-heading"><?php esc_html_e('الفصول', 'manga-overlay-theme'); ?></h2>
                        </div>
                        <span><?php echo esc_html(sprintf(__('%d فصلًا', 'manga-overlay-theme'), $totalChapters)); ?></span>
                    </header>

                    <?php if (is_array($chaptersResult['error'] ?? null)) : ?>
                        <div class="mol-notice mol-notice--error" role="alert"><?php esc_html_e('تعذر تحميل الفصول الآن.', 'manga-overlay-theme'); ?></div>
                    <?php elseif (empty($chapters)) : ?>
                        <div class="mol-empty-state mol-empty-state--compact">
                            <h3><?php esc_html_e('لا توجد فصول منشورة بعد', 'manga-overlay-theme'); ?></h3>
                            <p><?php esc_html_e('ستظهر الفصول هنا بمجرد نشرها.', 'manga-overlay-theme'); ?></p>
                        </div>
                    <?php else : ?>
                        <ol class="mol-chapter-list" start="<?php echo esc_attr((string) ((($chapterPage - 1) * 50) + 1)); ?>">
                            <?php foreach ($chapters as $chapter) : ?>
                                <?php
                                if (! is_array($chapter)) {
                                    continue;
                                }
                                $status = (string) ($chapter['translation_status'] ?? 'untranslated');
                                $readerMode = (string) ($chapter['reader_mode_override'] ?? $work['default_reader_mode'] ?? 'webtoon');
                                $chapterTitle = is_string($chapter['title'] ?? null) && '' !== $chapter['title']
                                    ? $chapter['title']
                                    : sprintf(__('الفصل %s', 'manga-overlay-theme'), (string) ($chapter['chapter_label'] ?? ''));
                                $chapterUrl = mol_theme_chapter_url($workId, (string) ($chapter['slug'] ?? ''));
                                ?>
                                <li class="mol-chapter-row">
                                    <a class="mol-chapter-row__main" href="<?php echo esc_url($chapterUrl); ?>">
                                        <span class="mol-chapter-row__number" dir="auto"><?php echo esc_html((string) ($chapter['chapter_label'] ?? '')); ?></span>
                                        <span>
                                            <strong dir="auto"><?php echo esc_html($chapterTitle); ?></strong>
                                            <?php if (! empty($chapter['published_at'])) : ?>
                                                <time datetime="<?php echo esc_attr((string) $chapter['published_at']); ?>"><?php echo esc_html(mol_theme_format_date((string) $chapter['published_at'])); ?></time>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                    <span class="mol-status mol-status--<?php echo esc_attr(sanitize_html_class($status)); ?>"><?php echo esc_html(mol_theme_translation_status_label($status)); ?></span>
                                    <span class="mol-reader-mode" title="<?php echo esc_attr(mol_theme_reader_mode_label($readerMode)); ?>">
                                        <?php echo mol_theme_icon($readerMode); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                                        <span class="screen-reader-text"><?php echo esc_html(mol_theme_reader_mode_label($readerMode)); ?></span>
                                    </span>
                                    <?php if (function_exists('mol_user_can_edit_chapter') && mol_user_can_edit_chapter(get_current_user_id(), (int) ($chapter['id'] ?? 0))) : ?>
                                        <a class="mol-chapter-row__edit" href="<?php echo esc_url(mol_theme_chapter_url($workId, (string) ($chapter['slug'] ?? ''), true)); ?>"><?php esc_html_e('تحرير', 'manga-overlay-theme'); ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php
                        echo mol_theme_pagination_markup(
                            $chapterPage,
                            $chapterTotalPages,
                            (string) get_permalink($workId),
                            array(),
                            'chapter_page'
                        ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by helper.
                        ?>
                    <?php endif; ?>
                </section>
            </div>
        </article>
    <?php endif; ?>
</main>
<?php
get_footer();
