<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\DatabaseException;
use MOL\Database\TableNames;
use MOL\Database\TransactionManager;
use MOL\Repositories\PageRepository;

final class ContentDeletionService
{
    private readonly TableNames $tables;
    private readonly PageRepository $pages;

    public function __construct(
        private readonly \wpdb $database,
        private readonly TransactionManager $transactions
    ) {
        $this->tables = new TableNames($database->prefix);
        $this->pages = new PageRepository($database);
    }

    public function deletePage(int $pageId): bool
    {
        return $this->transactions->run(function () use ($pageId): bool {
            $page = $this->database->get_row($this->prepare(
                "SELECT * FROM {$this->tables->pages} WHERE id = %d FOR UPDATE",
                $pageId
            ), ARRAY_A);
            $this->throwOnError('Locking the page for deletion');
            if (! is_array($page)) {
                return false;
            }
            $chapterId = (int) $page['chapter_id'];

            $this->database->get_results($this->prepare(
                "SELECT id FROM {$this->tables->elements} WHERE page_id = %d FOR UPDATE",
                $pageId
            ), ARRAY_A);
            $this->throwOnError('Locking page elements for deletion');

            $this->execute(
                "DELETE locks FROM {$this->tables->elementLocks} locks
                 INNER JOIN {$this->tables->elements} elements ON elements.id = locks.element_id
                 WHERE elements.page_id = {$pageId}",
                'Deleting page element locks'
            );
            $this->execute(
                "DELETE contributions FROM {$this->tables->contributions} contributions
                 INNER JOIN {$this->tables->elements} elements ON elements.id = contributions.element_id
                 WHERE elements.page_id = {$pageId}",
                'Deleting page contributions'
            );
            $this->execute(
                "DELETE reports FROM {$this->tables->reports} reports
                 LEFT JOIN {$this->tables->elements} elements ON elements.id = reports.element_id
                 WHERE reports.page_id = {$pageId} OR elements.page_id = {$pageId}",
                'Deleting page reports'
            );
            $this->execute(
                $this->prepare("DELETE FROM {$this->tables->elements} WHERE page_id = %d", $pageId),
                'Deleting page elements'
            );
            $this->execute(
                $this->prepare(
                    "DELETE FROM {$this->tables->idempotencyKeys}
                     WHERE resource_type = 'page' AND resource_id = %d",
                    $pageId
                ),
                'Deleting page idempotency records'
            );
            $this->execute(
                $this->prepare("DELETE FROM {$this->tables->pages} WHERE id = %d", $pageId),
                'Deleting the page'
            );
            $this->compactPageOrder($chapterId);

            return true;
        });
    }

    public function deleteChapter(int $chapterId): bool
    {
        return $this->transactions->run(function () use ($chapterId): bool {
            $chapter = $this->database->get_row($this->prepare(
                "SELECT id FROM {$this->tables->chapters} WHERE id = %d FOR UPDATE",
                $chapterId
            ), ARRAY_A);
            $this->throwOnError('Locking the chapter for deletion');
            if (! is_array($chapter)) {
                return false;
            }

            $this->database->get_results($this->prepare(
                "SELECT id FROM {$this->tables->pages} WHERE chapter_id = %d FOR UPDATE",
                $chapterId
            ), ARRAY_A);
            $this->throwOnError('Locking chapter pages for deletion');
            $this->database->get_results($this->prepare(
                "SELECT elements.id FROM {$this->tables->elements} elements
                 INNER JOIN {$this->tables->pages} pages ON pages.id = elements.page_id
                 WHERE pages.chapter_id = %d FOR UPDATE",
                $chapterId
            ), ARRAY_A);
            $this->throwOnError('Locking chapter elements for deletion');

            $this->execute(
                "DELETE locks FROM {$this->tables->elementLocks} locks
                 INNER JOIN {$this->tables->elements} elements ON elements.id = locks.element_id
                 INNER JOIN {$this->tables->pages} pages ON pages.id = elements.page_id
                 WHERE pages.chapter_id = {$chapterId}",
                'Deleting chapter element locks'
            );
            $this->execute(
                "DELETE contributions FROM {$this->tables->contributions} contributions
                 LEFT JOIN {$this->tables->elements} elements ON elements.id = contributions.element_id
                 LEFT JOIN {$this->tables->pages} pages ON pages.id = elements.page_id
                 WHERE contributions.chapter_id = {$chapterId} OR pages.chapter_id = {$chapterId}",
                'Deleting chapter contributions'
            );
            $this->execute(
                $this->prepare("DELETE FROM {$this->tables->reports} WHERE chapter_id = %d", $chapterId),
                'Deleting chapter reports'
            );
            $this->execute(
                "DELETE elements FROM {$this->tables->elements} elements
                 INNER JOIN {$this->tables->pages} pages ON pages.id = elements.page_id
                 WHERE pages.chapter_id = {$chapterId}",
                'Deleting chapter elements'
            );
            $this->execute(
                $this->prepare(
                    "DELETE idempotency FROM {$this->tables->idempotencyKeys} idempotency
                     INNER JOIN {$this->tables->pages} pages
                        ON idempotency.resource_type = 'page' AND idempotency.resource_id = pages.id
                     WHERE pages.chapter_id = %d",
                    $chapterId
                ),
                'Deleting chapter page idempotency records'
            );
            $this->execute(
                $this->prepare("DELETE FROM {$this->tables->pages} WHERE chapter_id = %d", $chapterId),
                'Deleting chapter pages'
            );
            $this->execute(
                $this->prepare("DELETE FROM {$this->tables->readingProgress} WHERE chapter_id = %d", $chapterId),
                'Deleting chapter reading progress'
            );
            $this->execute(
                $this->prepare(
                    "DELETE FROM {$this->tables->idempotencyKeys} WHERE scope = %s",
                    'page-upload:' . $chapterId
                ),
                'Deleting chapter upload idempotency records'
            );
            $this->execute(
                $this->prepare("DELETE FROM {$this->tables->chapters} WHERE id = %d", $chapterId),
                'Deleting the chapter'
            );

            return true;
        });
    }

    private function compactPageOrder(int $chapterId): void
    {
        $remaining = $this->pages->lockForChapter($chapterId);
        if (array() === $remaining) {
            return;
        }
        $maxIndex = max(array_column($remaining, 'page_index'));
        $offset = $maxIndex + count($remaining) + 2;
        $this->pages->moveToTemporaryRange($chapterId, $offset);
        $this->pages->assignFinalOrder($chapterId, array_column($remaining, 'id'));
    }

    private function execute(string $query, string $operation): void
    {
        if (false === $this->database->query($query)) {
            throw DatabaseException::fromWpdb($this->database, $operation);
        }
    }

    private function prepare(string $query, mixed ...$arguments): string
    {
        $prepared = $this->database->prepare($query, ...$arguments);
        if (! is_string($prepared)) {
            throw new \RuntimeException('WordPress could not prepare a database query.');
        }

        return $prepared;
    }

    private function throwOnError(string $operation): void
    {
        if ('' !== $this->database->last_error) {
            throw DatabaseException::fromWpdb($this->database, $operation);
        }
    }
}
