<?php

declare(strict_types=1);

namespace MOL\REST;

final class ChapterPresenter
{
    /** @param array<string, mixed> $chapter @return array<string, mixed> */
    public static function one(array $chapter): array
    {
        return array(
            'id' => (int) $chapter['id'],
            'work_id' => (int) $chapter['work_id'],
            'chapter_label' => (string) $chapter['chapter_label'],
            'sort_order' => (float) $chapter['sort_order'],
            'title' => null === $chapter['title'] ? null : (string) $chapter['title'],
            'slug' => (string) $chapter['slug'],
            'translation_status' => (string) $chapter['translation_status'],
            'source_lang_override' => null === $chapter['source_lang_override']
                ? null
                : (string) $chapter['source_lang_override'],
            'reader_mode_override' => null === $chapter['reader_mode_override']
                ? null
                : (string) $chapter['reader_mode_override'],
            'direction_override' => null === $chapter['direction_override']
                ? null
                : (string) $chapter['direction_override'],
            'is_published' => (bool) $chapter['is_published'],
            'published_at' => PresenterSupport::dateTime($chapter['published_at']),
            'created_at' => PresenterSupport::dateTime($chapter['created_at']),
            'updated_at' => PresenterSupport::dateTime($chapter['updated_at']),
        );
    }

    /** @param list<array<string, mixed>> $chapters @return list<array<string, mixed>> */
    public static function many(array $chapters): array
    {
        return array_map(self::one(...), $chapters);
    }
}
