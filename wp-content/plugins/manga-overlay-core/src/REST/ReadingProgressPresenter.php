<?php

declare(strict_types=1);

namespace MOL\REST;

final class ReadingProgressPresenter
{
    /** @param array<string, mixed> $progress @return array<string, mixed> */
    public static function one(array $progress): array
    {
        return array(
            'chapter_id' => (int) $progress['chapter_id'],
            'page_index' => (int) $progress['page_index'],
            'progress_unit' => (int) $progress['progress_unit'],
            'reader_mode' => (string) $progress['reader_mode'],
            'updated_at' => PresenterSupport::dateTime($progress['updated_at']) ?? '',
        );
    }
}
