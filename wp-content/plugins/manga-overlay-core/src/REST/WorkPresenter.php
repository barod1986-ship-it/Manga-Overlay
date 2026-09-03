<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Content\WorkContent;
use MOL\Content\WorkMeta;

final class WorkPresenter
{
    /**
     * @param array<string, mixed> $chapterSummary
     * @return array<string, mixed>
     */
    public static function summary(\WP_Post $work, array $chapterSummary): array
    {
        $latest = $chapterSummary['latest_published_chapter_at'] ?? null;

        return array(
            'id' => (int) $work->ID,
            'slug' => (string) $work->post_name,
            'title' => get_the_title($work),
            'type' => self::firstTermSlug((int) $work->ID, WorkContent::WORK_TYPE_TAXONOMY, 'other'),
            'genres' => self::termSlugs((int) $work->ID, WorkContent::GENRE_TAXONOMY),
            'source_language' => self::firstTermSlug(
                (int) $work->ID,
                WorkContent::SOURCE_LANGUAGE_TAXONOMY
            ),
            'work_status' => self::firstTermSlug((int) $work->ID, WorkContent::WORK_STATUS_TAXONOMY),
            'cover' => self::cover($work),
            'translation_summary' => array(
                'total' => (int) ($chapterSummary['total'] ?? 0),
                'untranslated' => (int) ($chapterSummary['untranslated'] ?? 0),
                'in_progress' => (int) ($chapterSummary['in_progress'] ?? 0),
                'completed' => (int) ($chapterSummary['completed'] ?? 0),
                'needs_review' => (int) ($chapterSummary['needs_review'] ?? 0),
            ),
            'latest_published_chapter_at' => PresenterSupport::dateTime($latest),
            'read_count' => null,
        );
    }

    /**
     * @param array<string, mixed> $chapterSummary
     * @return array<string, mixed>
     */
    public static function detail(\WP_Post $work, array $chapterSummary): array
    {
        $summary = self::summary($work, $chapterSummary);
        $altTitles = get_post_meta((int) $work->ID, WorkMeta::ALT_TITLES, true);
        $readerMode = get_post_meta((int) $work->ID, WorkMeta::DEFAULT_READER_MODE, true);
        $direction = get_post_meta((int) $work->ID, WorkMeta::READING_DIRECTION, true);

        $summary['description'] = wp_kses_post(apply_filters('the_content', (string) $work->post_content));
        $summary['alt_titles'] = WorkMeta::sanitizeAltTitles($altTitles);
        $summary['default_reader_mode'] = WorkMeta::sanitizeReaderMode($readerMode);
        $summary['reading_direction'] = WorkMeta::sanitizeReadingDirection($direction);

        return $summary;
    }

    /** @return list<string> */
    private static function termSlugs(int $workId, string $taxonomy): array
    {
        $terms = wp_get_post_terms($workId, $taxonomy, array('fields' => 'slugs'));
        if (is_wp_error($terms) || ! is_array($terms)) {
            return array();
        }

        return array_values(array_filter($terms, 'is_string'));
    }

    private static function firstTermSlug(int $workId, string $taxonomy, string $fallback = ''): string
    {
        $slugs = self::termSlugs($workId, $taxonomy);

        return $slugs[0] ?? $fallback;
    }

    /** @return array<string, mixed> */
    private static function cover(\WP_Post $work): array
    {
        $attachmentId = (int) get_post_thumbnail_id($work);
        $source = $attachmentId > 0 ? wp_get_attachment_image_src($attachmentId, 'full') : false;
        if (is_array($source)
            && is_string($source[0] ?? null)
            && (int) ($source[1] ?? 0) > 0
            && (int) ($source[2] ?? 0) > 0
        ) {
            $srcset = wp_get_attachment_image_srcset($attachmentId, 'full');
            $sizes = wp_get_attachment_image_sizes($attachmentId, 'full');
            $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);

            return array(
                'attachment_id' => $attachmentId,
                'url' => $source[0],
                'width' => (int) $source[1],
                'height' => (int) $source[2],
                'srcset' => is_string($srcset) && '' !== $srcset ? $srcset : null,
                'sizes' => is_string($sizes) && '' !== $sizes ? $sizes : null,
                'alt' => is_string($alt) && '' !== $alt ? $alt : get_the_title($work),
            );
        }

        return array(
            'attachment_id' => null,
            'url' => plugins_url('assets/placeholder-cover.svg', MOL_PLUGIN_FILE),
            'width' => 800,
            'height' => 1200,
            'srcset' => null,
            'sizes' => null,
            'alt' => get_the_title($work),
        );
    }
}
