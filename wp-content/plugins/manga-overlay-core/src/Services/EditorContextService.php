<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\WorkRepository;
use MOL\REST\ApiException;

final class EditorContextService
{
    public function __construct(
        private readonly WorkRepository $works,
        private readonly ChapterRepository $chapters,
        private readonly PageRepository $pages,
        private readonly ElementRepository $elements,
        private readonly ChapterVisibilityPolicy $visibility
    ) {
    }

    /**
     * @return array{
     *   work: \WP_Post,
     *   chapter: array<string, mixed>,
     *   pages: list<array{page: array<string, mixed>, elements: list<array<string, mixed>>}>
     * }
     */
    public function load(int $workId, string $chapterSlug, int $userId, string $targetLanguage = 'ar'): array
    {
        if (! $this->visibility->userCanEdit($userId)) {
            throw ApiException::forbidden();
        }
        if ($workId < 1 || '' === trim($chapterSlug) || '' === trim($targetLanguage)) {
            throw ApiException::notFound();
        }

        $work = $this->works->find($workId);
        $chapter = $this->chapters->findBySlug($workId, $chapterSlug);
        if (! $work instanceof \WP_Post || null === $chapter) {
            throw ApiException::notFound();
        }

        // Keep one visibility policy for public reads and authenticated editor reads.
        $this->visibility->assertVisible($chapter, 'publish' === $work->post_status);

        $pages = $this->pages->forChapter((int) $chapter['id']);
        $elements = $this->elements->forChapter((int) $chapter['id'], $targetLanguage);
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
            'work' => $work,
            'chapter' => $chapter,
            'pages' => $groups,
        );
    }
}
