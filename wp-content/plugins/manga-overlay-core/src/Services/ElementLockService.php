<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\TransactionManager;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ElementLockRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\REST\ApiException;

final class ElementLockService
{
    private const LEASE_SECONDS = 45;

    public function __construct(
        private readonly ChapterRepository $chapters,
        private readonly PageRepository $pages,
        private readonly ElementRepository $elements,
        private readonly ElementLockRepository $locks,
        private readonly TransactionManager $transactions,
        private readonly RateLimiter $rateLimiter = new RateLimiter()
    ) {
    }

    /** @return array{element_id: int, user_id: int, lock_token: string, expires_at: string} */
    public function acquire(int $elementId, int $userId): array
    {
        if ($userId < 1 || ! user_can($userId, 'mol_edit_translations')) {
            throw ApiException::forbidden();
        }
        $existing = $this->elements->find($elementId);
        if (null === $existing) {
            throw ApiException::notFound('Element not found.');
        }
        $pageId = (int) $existing['page_id'];
        $page = $this->pages->find($pageId);
        $chapter = null === $page ? null : $this->chapters->find((int) $page['chapter_id']);
        if (null === $page || null === $chapter) {
            throw ApiException::notFound('Element chapter not found.');
        }
        if (! apply_filters('mol_user_can_edit_chapter', true, $userId, $chapter)) {
            throw ApiException::forbidden();
        }
        $retryAfter = $this->rateLimiter->consumeLockAcquire($userId);
        if (null !== $retryAfter) {
            throw new ApiException(
                'mol_rate_limited',
                'Too many requests.',
                429,
                array('retry_after' => $retryAfter)
            );
        }

        return $this->transactions->run(function () use ($elementId, $pageId, $userId): array {
            if (null === $this->pages->lockForUpdate($pageId)) {
                throw ApiException::notFound('Element page not found.');
            }
            $element = $this->elements->lockForUpdate($elementId);
            if (null === $element || $pageId !== (int) $element['page_id']) {
                throw ApiException::notFound('Element not found.');
            }

            $now = current_time('mysql', true);
            $current = $this->locks->lockForUpdate($elementId);
            if (null !== $current && (string) $current['expires_at'] > $now) {
                if ($userId !== (int) $current['user_id']) {
                    throw new ApiException('mol_element_locked', 'The element is locked by another editor.', 423);
                }

                return $this->lease($current);
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = gmdate('Y-m-d H:i:s', time() + self::LEASE_SECONDS);
            if (null === $current) {
                $this->locks->insert($elementId, $userId, $token, $now, $expiresAt);
            } else {
                $this->locks->replace($elementId, $userId, $token, $now, $expiresAt);
            }

            return array(
                'element_id' => $elementId,
                'user_id' => $userId,
                'lock_token' => $token,
                'expires_at' => $expiresAt,
            );
        });
    }

    /** @param array<string, mixed> $lock @return array{element_id: int, user_id: int, lock_token: string, expires_at: string} */
    private function lease(array $lock): array
    {
        return array(
            'element_id' => (int) $lock['element_id'],
            'user_id' => (int) $lock['user_id'],
            'lock_token' => (string) $lock['lock_token'],
            'expires_at' => (string) $lock['expires_at'],
        );
    }
}
