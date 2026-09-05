<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\DatabaseException;
use MOL\Database\JsonDocument;
use MOL\Database\TransactionManager;
use MOL\Domain\ElementStyles;
use MOL\Domain\Validation\GeometryValidator;
use MOL\Domain\Validation\ValidationException;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementLockRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\IdempotencyKeyRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReportRepository;
use MOL\REST\ApiException;
use Throwable;

final class ElementWriteService
{
    public function __construct(
        private readonly ChapterRepository $chapters,
        private readonly PageRepository $pages,
        private readonly ElementRepository $elements,
        private readonly ElementLockRepository $locks,
        private readonly ContributionRepository $contributions,
        private readonly ReportRepository $reports,
        private readonly IdempotencyKeyRepository $idempotency,
        private readonly ElementStyleResolver $styles,
        private readonly TransactionManager $transactions,
        private readonly RateLimiter $rateLimiter = new RateLimiter()
    ) {
    }

    /**
     * @param array<string, mixed> $input Validated ElementCreate fields.
     * @return array<string, mixed>
     */
    public function create(int $userId, string $idempotencyKey, array $input): array
    {
        $this->assertIdempotencyKey($idempotencyKey);
        [$page, $chapter] = $this->editablePage((int) $input['page_id'], $userId, 'mol_edit_translations');
        unset($page);
        $requestHash = hash('sha256', JsonDocument::encode($this->canonical($input)));
        $scope = 'element-create:' . (int) $input['page_id'];
        $this->idempotency->deleteExpired(current_time('mysql', true));
        $existing = $this->idempotency->find($userId, $scope, $idempotencyKey);
        if (null !== $existing) {
            return $this->replay($existing, $requestHash);
        }
        $this->limitWrite($userId);

        try {
            return $this->transactions->run(function () use (
                $userId,
                $idempotencyKey,
                $input,
                $chapter,
                $requestHash,
                $scope
            ): array {
                $page = $this->pages->lockForUpdate((int) $input['page_id']);
                if (null === $page || (int) $page['chapter_id'] !== (int) $chapter['id']) {
                    throw ApiException::notFound('Page not found.');
                }
                $reservationId = $this->idempotency->insert(array(
                    'user_id' => $userId,
                    'scope' => $scope,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
                ));
                $style = $this->styles->resolve(
                    $userId,
                    $chapter,
                    (string) $input['element_type'],
                    is_int($input['preset_id']) ? $input['preset_id'] : null,
                    is_array($input['style']) ? $input['style'] : array()
                );
                $elementId = $this->elements->insert(array(
                    'page_id' => (int) $input['page_id'],
                    'target_lang' => (string) $input['target_lang'],
                    'element_type' => (string) $input['element_type'],
                    'x_unit' => (int) $input['x_unit'],
                    'y_unit' => (int) $input['y_unit'],
                    'w_unit' => (int) $input['w_unit'],
                    'h_unit' => (int) $input['h_unit'],
                    'rotation_mdeg' => (int) $input['rotation_mdeg'],
                    'z_index' => (int) $input['z_index'],
                    'content' => (string) $input['content'],
                    'style' => $style,
                    'created_by' => $userId,
                ));
                $this->contributions->upsert(
                    $elementId,
                    $userId,
                    (int) $chapter['work_id'],
                    (int) $chapter['id'],
                    true
                );
                $this->idempotency->complete(
                    $reservationId,
                    'element',
                    $elementId,
                    201,
                    array('element_id' => $elementId)
                );

                return $this->elements->find($elementId)
                    ?? throw new \RuntimeException('The created element could not be loaded.');
            });
        } catch (Throwable $error) {
            if ($error instanceof DatabaseException && str_contains(strtolower($error->getMessage()), 'duplicate')) {
                $record = $this->idempotency->find($userId, $scope, $idempotencyKey);
                if (null !== $record) {
                    return $this->replay($record, $requestHash);
                }
            }

            throw $error;
        }
    }

    /** @param array<string, mixed> $patch @return array<string, mixed> */
    public function update(
        int $elementId,
        int $userId,
        string $ifMatch,
        string $lockToken,
        array $patch
    ): array {
        $expectedVersion = $this->expectedVersion($ifMatch);
        $existing = $this->elements->find($elementId);
        if (null === $existing) {
            throw ApiException::notFound('Element not found.');
        }
        $pageId = (int) $existing['page_id'];
        $this->editablePage($pageId, $userId, 'mol_edit_translations');
        $this->limitWrite($userId);

        return $this->transactions->run(function () use (
            $elementId,
            $pageId,
            $userId,
            $expectedVersion,
            $lockToken,
            $patch
        ): array {
            [, $chapter] = $this->editablePage($pageId, $userId, 'mol_edit_translations', true);
            $element = $this->elements->lockForUpdate($elementId);
            if (null === $element || $pageId !== (int) $element['page_id']) {
                throw ApiException::notFound('Element not found.');
            }
            $this->assertActiveLock($elementId, $userId, $lockToken);
            if ($expectedVersion !== (int) $element['version']) {
                throw new ApiException('mol_version_conflict', 'The element version is stale.', 412);
            }
            if (array_key_exists('element_type', $patch)
                && (string) $patch['element_type'] !== (string) $element['element_type']
            ) {
                throw ApiException::invalidParams('element_type is immutable and must match the stored element.');
            }

            $next = $element;
            foreach (array('x_unit', 'y_unit', 'w_unit', 'h_unit', 'rotation_mdeg', 'z_index', 'content') as $field) {
                if (array_key_exists($field, $patch)) {
                    $next[$field] = $patch[$field];
                }
            }
            $currentStyle = is_array($element['style']) ? $element['style'] : array();
            $next['style'] = array_key_exists('style', $patch) && is_array($patch['style'])
                ? ElementStyles::resolve((string) $element['element_type'], $currentStyle, $patch['style'])
                : ElementStyles::resolve((string) $element['element_type'], $currentStyle);
            try {
                GeometryValidator::validate($next);
            } catch (ValidationException $error) {
                throw ApiException::invalidParams($error->getMessage());
            }

            $nextVersion = (int) $element['version'] + 1;
            $this->elements->update($elementId, array(
                'x_unit' => (int) $next['x_unit'],
                'y_unit' => (int) $next['y_unit'],
                'w_unit' => (int) $next['w_unit'],
                'h_unit' => (int) $next['h_unit'],
                'rotation_mdeg' => (int) $next['rotation_mdeg'],
                'z_index' => (int) $next['z_index'],
                'content' => (string) $next['content'],
                'style' => $next['style'],
                'version' => $nextVersion,
                'updated_by' => $userId,
                'updated_at' => current_time('mysql', true),
            ));
            $this->contributions->upsert(
                $elementId,
                $userId,
                (int) $chapter['work_id'],
                (int) $chapter['id'],
                false
            );

            return $this->elements->find($elementId)
                ?? throw new \RuntimeException('The updated element could not be loaded.');
        });
    }

    public function delete(int $elementId, int $userId, string $ifMatch, string $lockToken): void
    {
        $expectedVersion = $this->expectedVersion($ifMatch);
        $existing = $this->elements->find($elementId);
        if (null === $existing) {
            throw ApiException::notFound('Element not found.');
        }
        $pageId = (int) $existing['page_id'];
        $this->editablePage($pageId, $userId, 'mol_delete_translation_elements');
        $this->limitWrite($userId);

        $this->transactions->run(function () use (
            $elementId,
            $pageId,
            $userId,
            $expectedVersion,
            $lockToken
        ): void {
            $this->editablePage($pageId, $userId, 'mol_delete_translation_elements', true);
            $element = $this->elements->lockForUpdate($elementId);
            if (null === $element || $pageId !== (int) $element['page_id']) {
                throw ApiException::notFound('Element not found.');
            }
            $this->assertActiveLock($elementId, $userId, $lockToken);
            if ($expectedVersion !== (int) $element['version']) {
                throw new ApiException('mol_version_conflict', 'The element version is stale.', 412);
            }

            $this->locks->deleteForElement($elementId);
            $this->contributions->deleteForElement($elementId);
            $this->reports->deleteForElement($elementId);
            if (! $this->elements->delete($elementId)) {
                throw ApiException::notFound('Element not found.');
            }
        });
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function editablePage(
        int $pageId,
        int $userId,
        string $capability,
        bool $insideTransaction = false
    ): array {
        if ($userId < 1 || ! user_can($userId, $capability)) {
            throw ApiException::forbidden();
        }
        $page = $insideTransaction ? $this->pages->lockForUpdate($pageId) : $this->pages->find($pageId);
        if (null === $page) {
            throw ApiException::notFound('Page not found.');
        }
        $chapter = $this->chapters->find((int) $page['chapter_id']);
        if (null === $chapter) {
            throw ApiException::notFound('Chapter not found.');
        }
        if (! apply_filters('mol_user_can_edit_chapter', true, $userId, $chapter)) {
            throw ApiException::forbidden();
        }

        return array($page, $chapter);
    }

    private function assertActiveLock(int $elementId, int $userId, string $lockToken): void
    {
        $now = current_time('mysql', true);
        $lock = $this->locks->lockForUpdate($elementId);
        if (null === $lock
            || (string) $lock['expires_at'] <= $now
            || $userId !== (int) $lock['user_id']
            || '' === $lockToken
            || ! hash_equals((string) $lock['lock_token'], $lockToken)
        ) {
            throw new ApiException('mol_element_locked', 'The element is locked by another editor.', 423);
        }
    }

    private function expectedVersion(string $ifMatch): int
    {
        if ('' === trim($ifMatch)) {
            throw new ApiException(
                'mol_precondition_required',
                'If-Match is required for this operation.',
                428
            );
        }
        if (1 !== preg_match('/^"([1-9][0-9]*)"$/D', trim($ifMatch), $matches)) {
            throw new ApiException('mol_version_conflict', 'The element version is stale.', 412);
        }

        $version = filter_var($matches[1], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if (! is_int($version)) {
            throw new ApiException('mol_version_conflict', 'The element version is stale.', 412);
        }

        return $version;
    }

    private function limitWrite(int $userId): void
    {
        $retryAfter = $this->rateLimiter->consumeElementWrite($userId);
        if (null !== $retryAfter) {
            throw new ApiException(
                'mol_rate_limited',
                'Too many requests.',
                429,
                array('retry_after' => $retryAfter)
            );
        }
    }

    private function assertIdempotencyKey(string $key): void
    {
        if ('' === $key || strlen($key) > 100 || 1 === preg_match('/[\x00-\x1F\x7F]/', $key)) {
            throw ApiException::invalidParams('MOL-Idempotency-Key must contain 1 to 100 visible characters.');
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
        $elementId = (int) ($record['resource_id'] ?? 0);
        $element = 0 < $elementId ? $this->elements->find($elementId) : null;
        if (null === $element) {
            throw new ApiException(
                'mol_idempotency_mismatch',
                'The idempotency key no longer refers to an available element.',
                409
            );
        }

        return $element;
    }

    /** @param array<string, mixed> $value @return array<string, mixed> */
    private function canonical(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item) && ! array_is_list($item)) {
                /** @var array<string, mixed> $item */
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}
