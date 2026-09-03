<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\WorkRepository;
use MOL\REST\ApiException;

final class PublicReadService
{
    public function __construct(
        private readonly WorkRepository $works,
        private readonly ChapterRepository $chapters,
        private readonly PageRepository $pages,
        private readonly ElementRepository $elements,
        private readonly ContributionRepository $contributions,
        private readonly ChapterVisibilityPolicy $visibility
    ) {
    }

    /** @return array{chapter: array<string, mixed>, public: bool} */
    public function chapter(int $chapterId): array
    {
        [$chapter, $public] = $this->visibleChapter($chapterId);

        return array('chapter' => $chapter, 'public' => $public);
    }

    /** @return array{chapter: array<string, mixed>, pages: list<array<string, mixed>>, public: bool} */
    public function chapterPages(int $chapterId): array
    {
        [$chapter, $public] = $this->visibleChapter($chapterId);

        return array(
            'chapter' => $chapter,
            'pages' => $this->pages->forChapter($chapterId),
            'public' => $public,
        );
    }

    /**
     * @return array{
     *   chapter: array<string, mixed>,
     *   page: array<string, mixed>,
     *   elements: list<array<string, mixed>>,
     *   public: bool
     * }
     */
    public function pageElements(int $pageId, string $targetLanguage): array
    {
        $page = $this->pages->find($pageId);
        if (null === $page) {
            throw ApiException::notFound();
        }
        [$chapter, $public] = $this->visibleChapter((int) $page['chapter_id']);

        return array(
            'chapter' => $chapter,
            'page' => $page,
            'elements' => $this->elements->forPage($pageId, $targetLanguage),
            'public' => $public,
        );
    }

    /**
     * @return array{
     *   chapter: array<string, mixed>,
     *   pages: list<array{page: array<string, mixed>, elements: list<array<string, mixed>>}>,
     *   element_count: int,
     *   public: bool
     * }
     */
    public function chapterElements(int $chapterId, string $targetLanguage): array
    {
        [$chapter, $public] = $this->visibleChapter($chapterId);
        $pages = $this->pages->forChapter($chapterId);
        $elements = $this->elements->forChapter($chapterId, $targetLanguage);
        $elementsByPage = array();
        foreach ($elements as $element) {
            $elementsByPage[(int) $element['page_id']][] = $element;
        }
        $groups = array_map(
            static fn (array $page): array => array(
                'page' => $page,
                'elements' => array_values($elementsByPage[(int) $page['id']] ?? array()),
            ),
            $pages
        );

        return array(
            'chapter' => $chapter,
            'pages' => $groups,
            'element_count' => count($elements),
            'public' => $public,
        );
    }

    /**
     * @return array{
     *   chapter: array<string, mixed>,
     *   contributors: list<array{user_id: int, element_count: int}>,
     *   public: bool
     * }
     */
    public function chapterContributors(int $chapterId): array
    {
        [$chapter, $public] = $this->visibleChapter($chapterId);

        return array(
            'chapter' => $chapter,
            'contributors' => $this->contributions->contributorsForChapter($chapterId),
            'public' => $public,
        );
    }

    public function userCanEditChapter(int $userId, int $chapterId): bool
    {
        return null !== $this->chapters->find($chapterId) && $this->visibility->userCanEdit($userId);
    }

    /** @return array{0: array<string, mixed>, 1: bool} */
    private function visibleChapter(int $chapterId): array
    {
        $chapter = $this->chapters->find($chapterId);
        if (null === $chapter) {
            throw ApiException::notFound();
        }
        $work = $this->works->find((int) $chapter['work_id']);
        if (null === $work) {
            throw ApiException::notFound();
        }
        $public = $this->visibility->assertVisible($chapter, 'publish' === $work->post_status);

        return array($chapter, $public);
    }
}
