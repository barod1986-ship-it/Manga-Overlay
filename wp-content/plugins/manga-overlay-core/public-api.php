<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('mol_get_work_chapters')) {
    /** @param array<string, mixed> $args @return list<array<string, mixed>> */
    function mol_get_work_chapters(int $work_id, array $args = array()): array
    {
        return MOL\PublicApi::workChapters($work_id, $args);
    }
}

if (! function_exists('mol_get_chapter')) {
    /** @return array<string, mixed>|null */
    function mol_get_chapter(int $chapter_id): ?array
    {
        return MOL\PublicApi::chapter($chapter_id);
    }
}

if (! function_exists('mol_get_chapter_pages')) {
    /** @return list<array<string, mixed>> */
    function mol_get_chapter_pages(int $chapter_id): array
    {
        return MOL\PublicApi::chapterPages($chapter_id);
    }
}

if (! function_exists('mol_get_page_elements')) {
    /** @return list<array<string, mixed>> */
    function mol_get_page_elements(int $page_id, string $lang = 'ar'): array
    {
        return MOL\PublicApi::pageElements($page_id, $lang);
    }
}

if (! function_exists('mol_get_chapter_elements')) {
    /** @return list<array{page_id: int, page_index: int, elements: list<array<string, mixed>>}> */
    function mol_get_chapter_elements(int $chapter_id, string $lang = 'ar'): array
    {
        return MOL\PublicApi::chapterElements($chapter_id, $lang);
    }
}

if (! function_exists('mol_get_chapter_contributors')) {
    /** @return list<array<string, mixed>> */
    function mol_get_chapter_contributors(int $chapter_id): array
    {
        return MOL\PublicApi::chapterContributors($chapter_id);
    }
}

if (! function_exists('mol_user_can_edit_chapter')) {
    function mol_user_can_edit_chapter(int $user_id, int $chapter_id): bool
    {
        return MOL\PublicApi::userCanEditChapter($user_id, $chapter_id);
    }
}
