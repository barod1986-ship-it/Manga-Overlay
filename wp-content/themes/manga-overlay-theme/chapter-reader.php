<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$workId = get_queried_object_id();
$chapterSlug = (string) get_query_var('mol_chapter');
$readerResult = mol_theme_reader_context($workId, $chapterSlug);
$readerError = is_array($readerResult['error'] ?? null) ? $readerResult['error'] : null;

if (null !== $readerError) {
    $status = max(400, min(599, (int) ($readerResult['status'] ?? 404)));
    status_header($status);
    nocache_headers();
    if (404 === $status) {
        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            $wp_query->set_404();
        }
    }
    get_header('reader');
    ?>
    <main id="mol-reader-content" class="mol-reader-error">
        <a class="mol-reader-back" href="<?php echo esc_url((string) get_permalink($workId)); ?>">
            <?php echo mol_theme_icon('arrow-start'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            <?php esc_html_e('العودة إلى العمل', 'manga-overlay-theme'); ?>
        </a>
        <div role="alert">
            <span class="mol-kicker"><?php esc_html_e('تعذرت القراءة', 'manga-overlay-theme'); ?></span>
            <h1><?php esc_html_e('الفصل غير متاح', 'manga-overlay-theme'); ?></h1>
            <p><?php echo esc_html((string) ($readerError['message'] ?? 'تعذر تحميل الفصل المطلوب.')); ?></p>
        </div>
    </main>
    <?php
    get_footer('reader');
    return;
}

$reader = is_array($readerResult['data'] ?? null) ? $readerResult['data'] : array();
$work = is_array($reader['work'] ?? null) ? $reader['work'] : array();
$chapter = is_array($reader['chapter'] ?? null) ? $reader['chapter'] : array();
$chapters = is_array($reader['chapters'] ?? null) ? $reader['chapters'] : array();
$pages = is_array($reader['pages'] ?? null) ? $reader['pages'] : array();
$elementGroups = is_array($reader['elements'] ?? null) ? $reader['elements'] : array();
$contributors = is_array($reader['contributors'] ?? null) ? $reader['contributors'] : array();
$progress = is_array($reader['progress'] ?? null) ? $reader['progress'] : null;
$previousChapter = is_array($reader['previous_chapter'] ?? null) ? $reader['previous_chapter'] : null;
$nextChapter = is_array($reader['next_chapter'] ?? null) ? $reader['next_chapter'] : null;
$readerMode = in_array($reader['reader_mode'] ?? '', array('webtoon', 'paged'), true)
    ? (string) $reader['reader_mode']
    : 'webtoon';
$direction = in_array($reader['direction'] ?? '', array('rtl', 'ltr'), true)
    ? (string) $reader['direction']
    : 'rtl';
$elementCount = max(0, (int) ($reader['element_count'] ?? 0));
$hasTranslation = $elementCount > 0;
$workTitle = is_string($work['title'] ?? null) ? $work['title'] : get_the_title($workId);
$chapterTitle = is_string($chapter['title'] ?? null) && '' !== trim((string) $chapter['title'])
    ? $chapter['title']
    : sprintf(__('الفصل %s', 'manga-overlay-theme'), (string) ($chapter['chapter_label'] ?? ''));
$chapterId = max(0, (int) ($chapter['id'] ?? 0));
$workUrl = get_permalink($workId);
$workUrl = is_string($workUrl) ? $workUrl : mol_theme_library_url();

$readerPayload = array(
    'chapterId' => $chapterId,
    'workId' => $workId,
    'defaultMode' => $readerMode,
    'direction' => $direction,
    'hasTranslation' => $hasTranslation,
    'targetLanguage' => 'ar',
    'elementGroups' => $elementGroups,
    'initialProgress' => $progress,
    'isAuthenticated' => is_user_logged_in(),
    'progressEndpoint' => rest_url('mol/v1/reading-progress'),
    'restNonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
);
$readerJson = wp_json_encode(
    $readerPayload,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

get_header('reader');
?>
<main
    id="mol-reader-content"
    class="mol-reader"
    data-mol-reader
    data-mode="<?php echo esc_attr($readerMode); ?>"
    data-direction="<?php echo esc_attr($direction); ?>"
    data-translation="<?php echo $hasTranslation ? 'on' : 'off'; ?>"
>
    <header class="mol-reader-bar">
        <div class="mol-reader-bar__identity">
            <a class="mol-reader-back" href="<?php echo esc_url($workUrl); ?>">
                <?php echo mol_theme_icon('arrow-start'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                <span><?php esc_html_e('العمل', 'manga-overlay-theme'); ?></span>
            </a>
            <div>
                <span dir="auto"><?php echo esc_html($workTitle); ?></span>
                <strong dir="auto"><?php echo esc_html($chapterTitle); ?></strong>
            </div>
        </div>

        <div class="mol-reader-bar__tools">
            <div class="mol-reader-mode" role="group" aria-label="<?php esc_attr_e('نمط القراءة', 'manga-overlay-theme'); ?>">
                <button type="button" data-mol-mode="webtoon" aria-pressed="<?php echo 'webtoon' === $readerMode ? 'true' : 'false'; ?>">
                    <?php echo mol_theme_icon('webtoon'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                    <span><?php esc_html_e('شريط', 'manga-overlay-theme'); ?></span>
                </button>
                <button type="button" data-mol-mode="paged" aria-pressed="<?php echo 'paged' === $readerMode ? 'true' : 'false'; ?>">
                    <?php echo mol_theme_icon('paged'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                    <span><?php esc_html_e('صفحات', 'manga-overlay-theme'); ?></span>
                </button>
            </div>
            <button
                class="mol-reader-translation"
                type="button"
                data-mol-translation-toggle
                aria-pressed="<?php echo $hasTranslation ? 'true' : 'false'; ?>"
                <?php disabled(! $hasTranslation); ?>
            >
                <?php echo mol_theme_icon('layers'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                <span><?php echo esc_html($hasTranslation ? __('الترجمة ظاهرة', 'manga-overlay-theme') : __('لا توجد ترجمة', 'manga-overlay-theme')); ?></span>
            </button>
            <details class="mol-reader-chapters">
                <summary>
                    <?php echo mol_theme_icon('chapters'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                    <span><?php esc_html_e('الفصول', 'manga-overlay-theme'); ?></span>
                </summary>
                <nav aria-label="<?php esc_attr_e('قائمة فصول العمل', 'manga-overlay-theme'); ?>">
                    <ol>
                        <?php foreach ($chapters as $item) : ?>
                            <?php if (! is_array($item)) { continue; } ?>
                            <?php $isCurrent = (int) ($item['id'] ?? 0) === $chapterId; ?>
                            <li>
                                <a
                                    href="<?php echo esc_url(mol_theme_chapter_url($workId, (string) ($item['slug'] ?? ''))); ?>"
                                    <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>
                                >
                                    <span dir="auto"><?php echo esc_html((string) ($item['chapter_label'] ?? '')); ?></span>
                                    <strong dir="auto"><?php echo esc_html((string) ($item['title'] ?? sprintf(__('الفصل %s', 'manga-overlay-theme'), (string) ($item['chapter_label'] ?? '')))); ?></strong>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
            </details>
        </div>
    </header>

    <section class="mol-reader-meta" aria-label="<?php esc_attr_e('حالة الفصل', 'manga-overlay-theme'); ?>">
        <span class="mol-status mol-status--<?php echo esc_attr(sanitize_html_class((string) ($chapter['translation_status'] ?? 'untranslated'))); ?>">
            <?php echo esc_html(mol_theme_translation_status_label((string) ($chapter['translation_status'] ?? 'untranslated'))); ?>
        </span>
        <span><?php echo esc_html(sprintf(__('%d صفحة', 'manga-overlay-theme'), count($pages))); ?></span>
        <span><?php echo esc_html(sprintf(__('%d عنصر ترجمة', 'manga-overlay-theme'), $elementCount)); ?></span>
        <span data-mol-progress-status role="status" aria-live="polite"></span>
    </section>

    <?php if (empty($pages)) : ?>
        <section class="mol-reader-empty">
            <span class="mol-kicker"><?php esc_html_e('الفصل منشور', 'manga-overlay-theme'); ?></span>
            <h1><?php esc_html_e('لا توجد صفحات في هذا الفصل بعد', 'manga-overlay-theme'); ?></h1>
            <p><?php esc_html_e('ارجع إلى صفحة العمل واختر فصلًا آخر.', 'manga-overlay-theme'); ?></p>
            <a href="<?php echo esc_url($workUrl); ?>"><?php esc_html_e('العودة إلى العمل', 'manga-overlay-theme'); ?></a>
        </section>
    <?php else : ?>
        <section class="mol-reader-pages" data-mol-pages aria-label="<?php esc_attr_e('صفحات الفصل', 'manga-overlay-theme'); ?>">
            <?php foreach ($pages as $position => $page) : ?>
                <?php if (! is_array($page)) { continue; } ?>
                <?php $pageIndex = max(0, (int) ($page['page_index'] ?? $position)); ?>
                <figure
                    class="mol-reader-frame"
                    data-mol-page
                    data-page-id="<?php echo esc_attr((string) ((int) ($page['id'] ?? 0))); ?>"
                    data-page-index="<?php echo esc_attr((string) $pageIndex); ?>"
                >
                    <div class="mol-reader-viewport" data-mol-page-viewport>
                        <div class="mol-reader-surface" data-mol-page-surface>
                            <?php echo mol_theme_reader_image_markup($page, 0 === $position); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by helper. ?>
                            <div
                                class="mol-overlay-layer"
                                data-mol-overlay-layer
                                data-page-id="<?php echo esc_attr((string) ((int) ($page['id'] ?? 0))); ?>"
                                aria-label="<?php echo esc_attr(sprintf(__('ترجمة الصفحة %d', 'manga-overlay-theme'), $pageIndex + 1)); ?>"
                            ></div>
                        </div>
                    </div>
                    <figcaption><?php echo esc_html(sprintf(__('صفحة %d من %d', 'manga-overlay-theme'), $pageIndex + 1, count($pages))); ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </section>

        <nav class="mol-reader-page-controls" aria-label="<?php esc_attr_e('التنقل بين الصفحات', 'manga-overlay-theme'); ?>">
            <button type="button" data-mol-page-previous>
                <?php echo mol_theme_icon('arrow-start'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                <span><?php esc_html_e('الصفحة السابقة', 'manga-overlay-theme'); ?></span>
            </button>
            <span data-mol-page-counter aria-live="polite"></span>
            <button type="button" data-mol-page-next>
                <span><?php esc_html_e('الصفحة التالية', 'manga-overlay-theme'); ?></span>
                <?php echo mol_theme_icon('arrow-end'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            </button>
        </nav>

        <div class="mol-reader-zoom" role="group" aria-label="<?php esc_attr_e('تكبير الصفحة', 'manga-overlay-theme'); ?>">
            <button type="button" data-mol-zoom-out aria-label="<?php esc_attr_e('تصغير', 'manga-overlay-theme'); ?>">
                <?php echo mol_theme_icon('zoom-out'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            </button>
            <output data-mol-zoom-level>100%</output>
            <button type="button" data-mol-zoom-in aria-label="<?php esc_attr_e('تكبير', 'manga-overlay-theme'); ?>">
                <?php echo mol_theme_icon('zoom-in'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            </button>
            <button type="button" data-mol-zoom-reset aria-label="<?php esc_attr_e('إعادة ضبط التكبير', 'manga-overlay-theme'); ?>">
                <?php echo mol_theme_icon('reset'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
            </button>
        </div>
    <?php endif; ?>

    <nav class="mol-reader-chapter-nav" aria-label="<?php esc_attr_e('التنقل بين الفصول', 'manga-overlay-theme'); ?>">
        <div>
            <?php if (null !== $previousChapter) : ?>
                <a rel="prev" href="<?php echo esc_url(mol_theme_chapter_url($workId, (string) ($previousChapter['slug'] ?? ''))); ?>">
                    <?php echo mol_theme_icon('arrow-start'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                    <span><?php esc_html_e('الفصل السابق', 'manga-overlay-theme'); ?></span>
                </a>
            <?php endif; ?>
        </div>
        <a href="<?php echo esc_url($workUrl); ?>"><?php esc_html_e('صفحة العمل', 'manga-overlay-theme'); ?></a>
        <div>
            <?php if (null !== $nextChapter) : ?>
                <a rel="next" href="<?php echo esc_url(mol_theme_chapter_url($workId, (string) ($nextChapter['slug'] ?? ''))); ?>">
                    <span><?php esc_html_e('الفصل التالي', 'manga-overlay-theme'); ?></span>
                    <?php echo mol_theme_icon('arrow-end'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if (! empty($contributors)) : ?>
        <section class="mol-reader-contributors" aria-labelledby="mol-reader-contributors-heading">
            <header>
                <span class="mol-kicker"><?php esc_html_e('طبقة الترجمة', 'manga-overlay-theme'); ?></span>
                <h2 id="mol-reader-contributors-heading"><?php esc_html_e('مساهمو هذا الفصل', 'manga-overlay-theme'); ?></h2>
            </header>
            <ul>
                <?php foreach ($contributors as $contributor) : ?>
                    <?php if (! is_array($contributor)) { continue; } ?>
                    <?php
                    $username = (string) ($contributor['username'] ?? '');
                    $displayName = (string) ($contributor['display_name'] ?? $username);
                    $avatarUrl = is_string($contributor['avatar_url'] ?? null) ? $contributor['avatar_url'] : '';
                    ?>
                    <li>
                        <a href="<?php echo esc_url(mol_theme_profile_url($username)); ?>">
                            <?php if ('' !== $avatarUrl) : ?>
                                <img src="<?php echo esc_url($avatarUrl); ?>" width="48" height="48" loading="lazy" alt="">
                            <?php else : ?>
                                <span class="mol-reader-contributors__avatar" aria-hidden="true"><?php echo esc_html(mol_theme_initial($displayName)); ?></span>
                            <?php endif; ?>
                            <span>
                                <strong dir="auto"><?php echo esc_html($displayName); ?></strong>
                                <?php if (! empty($contributor['profile_tag'])) : ?>
                                    <small dir="auto"><?php echo esc_html((string) $contributor['profile_tag']); ?></small>
                                <?php endif; ?>
                            </span>
                            <b><?php echo esc_html(sprintf(__('%d عنصر', 'manga-overlay-theme'), (int) ($contributor['element_count'] ?? 0))); ?></b>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <script type="application/json" id="mol-reader-data"><?php echo false === $readerJson ? '{}' : $readerJson; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped for an application/json script. ?></script>
</main>
<?php
get_footer('reader');
