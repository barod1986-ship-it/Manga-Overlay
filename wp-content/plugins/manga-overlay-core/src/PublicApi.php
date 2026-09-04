<?php

declare(strict_types=1);

namespace MOL;

use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReadingProgressRepository;
use MOL\Repositories\WorkRepository;
use MOL\REST\ApiException;
use MOL\REST\ChapterPresenter;
use MOL\REST\ContributorPresenter;
use MOL\REST\ElementPresenter;
use MOL\REST\PagePresenter;
use MOL\REST\ReadingProgressPresenter;
use MOL\Services\PublicReadService;
use MOL\Services\ReadingProgressService;

final class PublicApi
{
    /** @param array<string, mixed> $arguments @return list<array<string, mixed>> */
    public static function workChapters(int $workId, array $arguments = array()): array
    {
        $repositories = self::repositories();
        if (null === $repositories || $workId < 1 || null === $repositories['works']->findPublished($workId)) {
            return array();
        }
        if (isset($arguments['page']) || isset($arguments['per_page'])) {
            $page = max(1, (int) ($arguments['page'] ?? 1));
            $perPage = max(1, min(100, (int) ($arguments['per_page'] ?? 24)));
            $result = $repositories['chapters']->forWorkPaginated($workId, $page, $perPage, true);

            return ChapterPresenter::many($result['items']);
        }

        return ChapterPresenter::many($repositories['chapters']->forWork($workId, true));
    }

    /** @return array<string, mixed>|null */
    public static function chapter(int $chapterId): ?array
    {
        $service = self::readService();
        if (null === $service || $chapterId < 1) {
            return null;
        }

        try {
            $result = $service->chapter($chapterId);

            return ChapterPresenter::one($result['chapter']);
        } catch (ApiException) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function chapterPages(int $chapterId): array
    {
        $service = self::readService();
        if (null === $service || $chapterId < 1) {
            return array();
        }

        try {
            return PagePresenter::many($service->chapterPages($chapterId)['pages']);
        } catch (ApiException) {
            return array();
        }
    }

    /** @return list<array<string, mixed>> */
    public static function pageElements(int $pageId, string $targetLanguage = 'ar'): array
    {
        $service = self::readService();
        if (null === $service || $pageId < 1 || '' === trim($targetLanguage)) {
            return array();
        }

        try {
            return ElementPresenter::many($service->pageElements($pageId, $targetLanguage)['elements']);
        } catch (ApiException) {
            return array();
        }
    }

    /** @return list<array{page_id: int, page_index: int, elements: list<array<string, mixed>>}> */
    public static function chapterElements(int $chapterId, string $targetLanguage = 'ar'): array
    {
        $service = self::readService();
        if (null === $service || $chapterId < 1 || '' === trim($targetLanguage)) {
            return array();
        }

        try {
            $result = $service->chapterElements($chapterId, $targetLanguage);

            return array_map(
                static fn (array $group): array => array(
                    'page_id' => (int) $group['page']['id'],
                    'page_index' => (int) $group['page']['page_index'],
                    'elements' => ElementPresenter::many($group['elements']),
                ),
                $result['pages']
            );
        } catch (ApiException) {
            return array();
        }
    }

    /** @return list<array<string, mixed>> */
    public static function chapterContributors(int $chapterId): array
    {
        $service = self::readService();
        if (null === $service || $chapterId < 1) {
            return array();
        }

        try {
            return ContributorPresenter::many($service->chapterContributors($chapterId)['contributors']);
        } catch (ApiException) {
            return array();
        }
    }

    /** @return array<string, mixed>|null */
    public static function readingProgress(int $userId, int $chapterId): ?array
    {
        $repositories = self::repositories();
        $reads = self::readService();
        if (null === $repositories
            || null === $reads
            || $userId < 1
            || $userId !== get_current_user_id()
            || $chapterId < 1
        ) {
            return null;
        }

        try {
            $progress = (new ReadingProgressService($repositories['reading_progress'], $reads))
                ->find($userId, $chapterId);

            return null === $progress ? null : ReadingProgressPresenter::one($progress);
        } catch (ApiException) {
            return null;
        }
    }

    public static function userCanEditChapter(int $userId, int $chapterId): bool
    {
        $service = self::readService();

        return null !== $service
            && $userId > 0
            && $chapterId > 0
            && $service->userCanEditChapter($userId, $chapterId);
    }

    private static function readService(): ?PublicReadService
    {
        $repositories = self::repositories();
        if (null === $repositories) {
            return null;
        }

        return new PublicReadService(
            $repositories['works'],
            $repositories['chapters'],
            $repositories['pages'],
            $repositories['elements'],
            $repositories['contributions'],
            new ChapterVisibilityPolicy()
        );
    }

    /**
     * @return array{
     *   works: WorkRepository,
     *   chapters: ChapterRepository,
     *   pages: PageRepository,
     *   elements: ElementRepository,
     *   contributions: ContributionRepository,
     *   reading_progress: ReadingProgressRepository
     * }|null
     */
    private static function repositories(): ?array
    {
        global $wpdb;
        if (! $wpdb instanceof \wpdb) {
            return null;
        }

        return array(
            'works' => new WorkRepository($wpdb),
            'chapters' => new ChapterRepository($wpdb),
            'pages' => new PageRepository($wpdb),
            'elements' => new ElementRepository($wpdb),
            'contributions' => new ContributionRepository($wpdb),
            'reading_progress' => new ReadingProgressRepository($wpdb),
        );
    }
}
