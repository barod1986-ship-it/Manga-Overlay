<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Content\WorkContent;
use MOL\Database\DatabaseException;
use MOL\Database\TransactionManager;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\IdempotencyKeyRepository;
use MOL\Repositories\PageRepository;
use MOL\REST\ApiException;
use Throwable;

final class PageUploadService
{
    public function __construct(
        private readonly ChapterRepository $chapters,
        private readonly PageRepository $pages,
        private readonly IdempotencyKeyRepository $idempotency,
        private readonly TransactionManager $transactions,
        private readonly MediaService $media = new MediaService(),
        private readonly RateLimiter $rateLimiter = new RateLimiter()
    ) {
    }

    /** @param array<string, mixed> $file @return array<string, mixed> */
    public function upload(int $chapterId, int $userId, string $idempotencyKey, array $file): array
    {
        if ($userId < 1) {
            throw ApiException::forbidden();
        }
        if ('' === $idempotencyKey || strlen($idempotencyKey) > 100 || preg_match('/[\x00-\x1F\x7F]/', $idempotencyKey)) {
            throw ApiException::invalidParams('MOL-Idempotency-Key must contain 1 to 100 visible characters.');
        }

        $chapter = $this->chapters->find($chapterId);
        if (null === $chapter) {
            throw ApiException::notFound('Chapter not found.');
        }
        $work = get_post($chapter['work_id']);
        if (! $work instanceof \WP_Post || WorkContent::POST_TYPE !== $work->post_type) {
            throw ApiException::notFound('Chapter work not found.');
        }
        if (! apply_filters('mol_user_can_upload_chapter', true, $userId, $chapter)) {
            throw ApiException::forbidden();
        }

        $inspected = $this->media->inspect($file);
        $requestHash = hash('sha256', implode("\0", array(
            (string) $chapterId,
            (string) $inspected['file']['name'],
            $inspected['mime'],
            (string) $inspected['file']['size'],
            $inspected['digest'],
        )));
        $scope = 'page-upload:' . $chapterId;
        $this->idempotency->deleteExpired(current_time('mysql', true));
        $existing = $this->idempotency->find($userId, $scope, $idempotencyKey);
        if (null !== $existing) {
            return $this->replay($existing, $requestHash);
        }

        $retryAfter = $this->rateLimiter->consumeUpload($userId);
        if (null !== $retryAfter) {
            throw new ApiException(
                'mol_rate_limited',
                'Too many requests.',
                429,
                array('retry_after' => $retryAfter)
            );
        }

        $stored = $this->media->store($inspected);
        try {
            return $this->transactions->run(function () use (
                $chapterId,
                $userId,
                $scope,
                $idempotencyKey,
                $requestHash,
                $stored
            ): array {
                if (null === $this->chapters->lockForUpdate($chapterId)) {
                    throw ApiException::notFound('Chapter not found.');
                }
                $reservationId = $this->idempotency->insert(array(
                    'user_id' => $userId,
                    'scope' => $scope,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
                ));
                $currentPages = $this->pages->lockForChapter($chapterId);
                $pageIndex = array() === $currentPages
                    ? 0
                    : max(array_column($currentPages, 'page_index')) + 1;
                $pageId = $this->pages->insert(
                    $chapterId,
                    $pageIndex,
                    $stored['attachment_id'],
                    $stored['width'],
                    $stored['height']
                );
                $this->idempotency->complete($reservationId, 'page', $pageId, 201, array('page_id' => $pageId));

                return $this->pages->find($pageId)
                    ?? throw new \RuntimeException('The uploaded page could not be loaded.');
            });
        } catch (Throwable $error) {
            $this->media->deleteAttachment($stored['attachment_id']);
            if ($error instanceof DatabaseException && str_contains(strtolower($error->getMessage()), 'duplicate')) {
                $record = $this->idempotency->find($userId, $scope, $idempotencyKey);
                if (null !== $record) {
                    return $this->replay($record, $requestHash);
                }
            }

            throw $error;
        }
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function replay(array $record, string $requestHash): array
    {
        if (! hash_equals((string) $record['request_hash'], $requestHash)) {
            throw new ApiException(
                'mol_idempotency_mismatch',
                'The idempotency key was already used with a different request payload.',
                409
            );
        }
        $pageId = (int) ($record['resource_id'] ?? 0);
        $page = 0 < $pageId ? $this->pages->find($pageId) : null;
        if (null === $page) {
            throw new ApiException(
                'mol_idempotency_mismatch',
                'The idempotency key no longer refers to an available page.',
                409
            );
        }

        return $page;
    }
}
