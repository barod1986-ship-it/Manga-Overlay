<?php

declare(strict_types=1);

namespace MOL\Repositories;

use MOL\Content\WorkContent;

final class WorkRepository extends AbstractRepository
{
    public function find(int $workId): ?\WP_Post
    {
        $this->positiveId($workId, 'work_id');
        $post = get_post($workId);
        if (! $post instanceof \WP_Post || WorkContent::POST_TYPE !== $post->post_type) {
            return null;
        }

        return $post;
    }

    public function findPublished(int $workId): ?\WP_Post
    {
        $post = $this->find($workId);

        return $post instanceof \WP_Post && 'publish' === $post->post_status ? $post : null;
    }

    /**
     * @param array{
     *   search: string,
     *   type: string,
     *   genres: list<string>,
     *   source_lang: string,
     *   work_status: string,
     *   translation_status: string,
     *   sort: string,
     *   page: int,
     *   per_page: int
     * } $filters
     * @return array{items: list<\WP_Post>, total: int, total_pages: int}
     */
    public function search(array $filters): array
    {
        $taxQuery = array('relation' => 'AND');
        $this->appendTaxonomyFilter($taxQuery, WorkContent::WORK_TYPE_TAXONOMY, $filters['type']);
        $this->appendTaxonomyFilter($taxQuery, WorkContent::GENRE_TAXONOMY, $filters['genres']);
        $this->appendTaxonomyFilter(
            $taxQuery,
            WorkContent::SOURCE_LANGUAGE_TAXONOMY,
            $filters['source_lang']
        );
        $this->appendTaxonomyFilter($taxQuery, WorkContent::WORK_STATUS_TAXONOMY, $filters['work_status']);

        $arguments = array(
            'post_type' => WorkContent::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $filters['per_page'],
            'paged' => $filters['page'],
            's' => $filters['search'],
            'ignore_sticky_posts' => true,
            'no_found_rows' => false,
            'suppress_filters' => false,
        );
        if (count($taxQuery) > 1) {
            $arguments['tax_query'] = $taxQuery;
        }

        if ('latest_work' === $filters['sort']) {
            $arguments['orderby'] = array('date' => 'DESC', 'ID' => 'DESC');
        } elseif ('title_asc' === $filters['sort']) {
            $arguments['orderby'] = array('title' => 'ASC', 'ID' => 'ASC');
        }

        $requiresChapterJoin = 'latest_chapter' === $filters['sort'] || '' !== $filters['translation_status'];
        $filter = null;
        if ($requiresChapterJoin) {
            $filter = function (array $clauses, \WP_Query $query) use ($filters): array {
                unset($query);
                $postsTable = $this->database->posts;
                $chapterAlias = 'mol_library_chapter';
                $clauses['join'] .= sprintf(
                    ' LEFT JOIN %s AS %s ON (%s.ID = %s.work_id AND %s.is_published = 1)',
                    $this->tables->chapters,
                    $chapterAlias,
                    $postsTable,
                    $chapterAlias,
                    $chapterAlias
                );
                if ('' !== $filters['translation_status']) {
                    $clauses['where'] .= $this->prepare(
                        " AND {$chapterAlias}.translation_status = %s",
                        $filters['translation_status']
                    );
                }
                $clauses['groupby'] = "{$postsTable}.ID";
                if ('latest_chapter' === $filters['sort']) {
                    $clauses['orderby'] = sprintf(
                        'MAX(%1$s.published_at) DESC, %2$s.post_date_gmt DESC, %2$s.ID DESC',
                        $chapterAlias,
                        $postsTable
                    );
                }

                return $clauses;
            };
            add_filter('posts_clauses', $filter, 10, 2);
        }

        try {
            $query = new \WP_Query($arguments);
        } finally {
            if (null !== $filter) {
                remove_filter('posts_clauses', $filter, 10);
            }
        }

        $items = array_values(array_filter(
            $query->posts,
            static fn (mixed $post): bool => $post instanceof \WP_Post
        ));

        return array(
            'items' => $items,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        );
    }

    /**
     * @param list<int> $workIds
     * @return array<int, array{
     *   total: int,
     *   untranslated: int,
     *   in_progress: int,
     *   completed: int,
     *   needs_review: int,
     *   latest_published_chapter_at: string|null
     * }>
     */
    public function chapterSummaries(array $workIds): array
    {
        $summaries = array();
        foreach ($workIds as $workId) {
            $this->positiveId($workId, 'work_id');
            $summaries[$workId] = self::emptyChapterSummary();
        }
        if (array() === $summaries) {
            return array();
        }

        $placeholders = implode(', ', array_fill(0, count($workIds), '%d'));
        $rows = $this->fetchAll($this->prepare(
            "SELECT work_id, translation_status, COUNT(*) AS status_count, MAX(published_at) AS latest_published_at
             FROM {$this->tables->chapters}
             WHERE is_published = 1 AND work_id IN ({$placeholders})
             GROUP BY work_id, translation_status",
            ...$workIds
        ));
        foreach ($rows as $row) {
            $workId = (int) $row['work_id'];
            $status = (string) $row['translation_status'];
            $count = (int) $row['status_count'];
            if (! isset($summaries[$workId]) || ! array_key_exists($status, $summaries[$workId])) {
                continue;
            }
            $summaries[$workId][$status] = $count;
            $summaries[$workId]['total'] += $count;
            $candidate = is_string($row['latest_published_at']) && '' !== $row['latest_published_at']
                ? $row['latest_published_at']
                : null;
            if (null !== $candidate
                && (null === $summaries[$workId]['latest_published_chapter_at']
                    || $candidate > $summaries[$workId]['latest_published_chapter_at'])
            ) {
                $summaries[$workId]['latest_published_chapter_at'] = $candidate;
            }
        }

        return $summaries;
    }

    /** @param array<int|string, mixed> $taxQuery */
    private function appendTaxonomyFilter(array &$taxQuery, string $taxonomy, string|array $terms): void
    {
        $terms = is_array($terms) ? $terms : ('' === $terms ? array() : array($terms));
        if (array() === $terms) {
            return;
        }

        $taxQuery[] = array(
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => array_values($terms),
            'operator' => 'IN',
        );
    }

    /**
     * @return array{
     *   total: int,
     *   untranslated: int,
     *   in_progress: int,
     *   completed: int,
     *   needs_review: int,
     *   latest_published_chapter_at: null
     * }
     */
    private static function emptyChapterSummary(): array
    {
        return array(
            'total' => 0,
            'untranslated' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'needs_review' => 0,
            'latest_published_chapter_at' => null,
        );
    }
}
