<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$work = isset($args['work']) && is_array($args['work']) ? $args['work'] : array();
$priority = ! empty($args['priority']);
$title = is_string($work['title'] ?? null) ? $work['title'] : '';
$type = is_string($work['type'] ?? null) ? $work['type'] : 'other';
$workStatus = is_string($work['work_status'] ?? null) ? $work['work_status'] : '';
$sourceLanguage = is_string($work['source_language'] ?? null) ? $work['source_language'] : '';
$cover = is_array($work['cover'] ?? null) ? $work['cover'] : array();
$summary = is_array($work['translation_summary'] ?? null) ? $work['translation_summary'] : array();
$total = max(0, (int) ($summary['total'] ?? 0));
$completed = max(0, min($total, (int) ($summary['completed'] ?? 0)));
$percent = mol_theme_translation_percent($summary);
$url = mol_theme_work_url($work);
$latestDate = mol_theme_format_date(is_string($work['latest_published_chapter_at'] ?? null) ? $work['latest_published_chapter_at'] : null);
?>
<article class="mol-work-card">
    <a class="mol-work-card__cover" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr(sprintf(__('فتح %s', 'manga-overlay-theme'), $title)); ?>">
        <?php echo mol_theme_cover_markup($cover, $priority); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by helper. ?>
        <span class="mol-work-card__type"><?php echo esc_html(mol_theme_work_type_label($type)); ?></span>
    </a>
    <div class="mol-work-card__body">
        <div class="mol-work-card__meta">
            <?php if ('' !== $workStatus) : ?>
                <span><?php echo esc_html(mol_theme_taxonomy_label('mol_work_status', $workStatus)); ?></span>
            <?php endif; ?>
            <?php if ('' !== $sourceLanguage) : ?>
                <span dir="auto"><?php echo esc_html(mol_theme_taxonomy_label('mol_source_language', $sourceLanguage)); ?></span>
            <?php endif; ?>
        </div>
        <h2 class="mol-work-card__title" dir="auto"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a></h2>
        <div class="mol-work-card__translation">
            <div class="mol-work-card__translation-copy">
                <span><?php esc_html_e('اكتمال الترجمة', 'manga-overlay-theme'); ?></span>
                <strong><?php echo esc_html((string) $percent); ?>%</strong>
            </div>
            <progress max="<?php echo esc_attr((string) max(1, $total)); ?>" value="<?php echo esc_attr((string) $completed); ?>">
                <?php echo esc_html((string) $percent); ?>%
            </progress>
            <div class="mol-work-card__chapter-note">
                <?php if ($total > 0) : ?>
                    <span><?php echo esc_html(sprintf(_n('%d فصل منشور', '%d فصلًا منشورًا', $total, 'manga-overlay-theme'), $total)); ?></span>
                <?php else : ?>
                    <span><?php esc_html_e('لا توجد فصول منشورة', 'manga-overlay-theme'); ?></span>
                <?php endif; ?>
                <?php if ('' !== $latestDate) : ?>
                    <time datetime="<?php echo esc_attr((string) ($work['latest_published_chapter_at'] ?? '')); ?>"><?php echo esc_html($latestDate); ?></time>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>

