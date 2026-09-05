<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Content\WorkContent;
use MOL\Database\DatabaseException;
use MOL\Repositories\ChapterRepository;
use MOL\REST\ApiException;

final class ChapterService
{
    private const SLUG_ATTEMPTS = 50;

    public function __construct(private readonly ChapterRepository $chapters)
    {
    }

    /** @param array<string, mixed> $chapter @return array<string, mixed> */
    public function create(array $chapter, int $userId): array
    {
        if ($userId < 1) {
            throw ApiException::forbidden();
        }
        $work = get_post($chapter['work_id']);
        if (! $work instanceof \WP_Post || WorkContent::POST_TYPE !== $work->post_type) {
            throw ApiException::notFound('Work not found.');
        }

        $base = '' !== trim((string) ($chapter['title'] ?? ''))
            ? sanitize_title((string) $chapter['title'])
            : sanitize_title('chapter-' . $chapter['chapter_label']);
        if ('' === $base) {
            $base = 'chapter';
        }

        $chapter['created_by'] = $userId;
        $chapter['published_at'] = ! empty($chapter['is_published']) ? current_time('mysql', true) : null;

        for ($attempt = 1; $attempt <= self::SLUG_ATTEMPTS; ++$attempt) {
            $slug = $this->slugCandidate($base, $attempt);
            if (null !== $this->chapters->findBySlug((int) $chapter['work_id'], $slug)) {
                continue;
            }

            $chapter['slug'] = $slug;
            try {
                $chapterId = $this->chapters->insert($chapter);

                return $this->chapters->find($chapterId)
                    ?? throw new \RuntimeException('The created chapter could not be loaded.');
            } catch (DatabaseException $error) {
                if (! $this->isDuplicateKey($error)) {
                    throw $error;
                }
            }
        }

        throw new ApiException(
            'mol_slug_conflict',
            'A unique chapter slug could not be generated. Retry with updated chapter metadata.',
            409
        );
    }

    /** @param array<string, mixed> $changes @return array<string, mixed> */
    public function update(int $chapterId, array $changes): array
    {
        $existing = $this->chapters->find($chapterId);
        if (null === $existing) {
            throw ApiException::notFound('Chapter not found.');
        }

        if (array_key_exists('is_published', $changes)) {
            if ($changes['is_published'] && ! $existing['is_published']) {
                $changes['published_at'] = current_time('mysql', true);
            } elseif (! $changes['is_published']) {
                $changes['published_at'] = null;
            }
        }
        $this->chapters->update($chapterId, $changes);

        return $this->chapters->find($chapterId)
            ?? throw new \RuntimeException('The updated chapter could not be loaded.');
    }

    /** @return array<string, mixed> */
    public function review(int $chapterId, string $translationStatus): array
    {
        if (null === $this->chapters->find($chapterId)) {
            throw ApiException::notFound('Chapter not found.');
        }
        $this->chapters->update($chapterId, array('translation_status' => $translationStatus));

        return $this->chapters->find($chapterId)
            ?? throw new \RuntimeException('The reviewed chapter could not be loaded.');
    }

    private function slugCandidate(string $base, int $attempt): string
    {
        $suffix = 1 === $attempt ? '' : '-' . $attempt;
        $candidate = substr($base, 0, 190 - strlen($suffix));
        $candidate = rtrim($candidate, '-');
        if ('' === $candidate) {
            $candidate = 'chapter';
        }

        return $candidate . $suffix;
    }

    private function isDuplicateKey(DatabaseException $error): bool
    {
        return str_contains(strtolower($error->getMessage()), 'duplicate');
    }
}
