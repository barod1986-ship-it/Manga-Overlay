<?php
declare(strict_types=1);

namespace MOL\Database;

use MOL\Domain\Fault;
use MOL\Media\ImageResource;

final class WorkRepository extends Repository
{
	public function library(array $input): array
	{
		$query = new \WP_Query();
		$args = ['post_type' => 'mol_work', 'post_status' => 'publish', 'posts_per_page' => $input['per_page'], 'paged' => $input['page'], 'ignore_sticky_posts' => true, 'cache_results' => false];
		$tax = ['relation' => 'AND'];
		foreach (['type' => 'mol_work_type', 'genre' => 'mol_genre', 'source_lang' => 'mol_source_language', 'work_status' => 'mol_work_status'] as $key => $taxonomy) {
			if (!empty($input[$key])) {
				$tax[] = ['taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => (array) $input[$key]];
			}
		}
		if (count($tax) > 1) {
			$args['tax_query'] = $tax;
		}
		$clauses = function (array $clauses, \WP_Query $current) use ($query, $input): array {
			if ($current !== $query) {
				return $clauses;
			}
			$posts = $this->db->prepare('%i', $this->db->posts);
			if (isset($input['search']) && $input['search'] !== '') {
				$like = '%' . $this->db->esc_like($input['search']) . '%';
				$clauses['where'] .= $this->db->prepare(
					' AND (' . $posts . '.post_title LIKE %s OR EXISTS (SELECT 1 FROM %i mol_alias WHERE mol_alias.post_id = ' . $posts . '.ID AND mol_alias.meta_key = %s AND mol_alias.meta_value LIKE %s))',
					$like, $this->db->postmeta, '_mol_alt_titles', $like
				);
			}
			if (isset($input['translation_status'])) {
				$clauses['where'] .= $this->db->prepare(
					' AND EXISTS (SELECT 1 FROM %i mol_filter_ch WHERE mol_filter_ch.work_id = ' . $posts . '.ID AND mol_filter_ch.is_published = 1 AND mol_filter_ch.translation_status = %s)',
					$this->tables->name('chapters'), $input['translation_status']
				);
			}
			if ($input['sort'] === 'latest_chapter') {
				$clauses['join'] .= $this->db->prepare(
					' LEFT JOIN (SELECT work_id, MAX(published_at) AS latest FROM %i WHERE is_published = 1 GROUP BY work_id) mol_latest ON mol_latest.work_id = ' . $posts . '.ID',
					$this->tables->name('chapters')
				);
				$clauses['orderby'] = 'mol_latest.latest DESC, ' . $posts . '.post_date_gmt DESC, ' . $posts . '.ID DESC';
			} elseif ($input['sort'] === 'title_asc') {
				$clauses['orderby'] = $posts . '.post_title ASC, ' . $posts . '.ID ASC';
			} else {
				$clauses['orderby'] = $posts . '.post_date_gmt DESC, ' . $posts . '.ID DESC';
			}
			return $clauses;
		};
		add_filter('posts_clauses', $clauses, 10, 2);
		try {
			$works = $query->query($args);
		} finally {
			remove_filter('posts_clauses', $clauses, 10);
		}
		$this->checked($works);
		$statistics = $this->statistics(array_map(static fn ($work) => (int) $work->ID, $works));
		return [
			'data' => array_map(fn ($work) => $this->to_dto($work, $statistics[$work->ID] ?? []), $works),
			'meta' => ['page' => $input['page'], 'per_page' => $input['per_page'], 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages, 'sort' => $input['sort'], 'most_read_available' => false],
		];
	}

	public function detail(int $id): array
	{
		$work = get_post($id);
		if (!$work || $work->post_type !== 'mol_work' || $work->post_status !== 'publish') {
			throw Fault::missing();
		}
		$stats = $this->statistics([$id]);
		$result = $this->to_dto($work, $stats[$id] ?? []);
		$titles = get_post_meta($id, '_mol_alt_titles', true);
		return $result + [
			'description' => wp_kses_post($work->post_content),
			'alt_titles' => is_array($titles) ? array_values($titles) : [],
			'default_reader_mode' => get_post_meta($id, '_mol_default_reader_mode', true) === 'paged' ? 'paged' : 'webtoon',
			'reading_direction' => get_post_meta($id, '_mol_reading_direction', true) === 'ltr' ? 'ltr' : 'rtl',
		];
	}

	private function statistics(array $ids): array
	{
		if (!$ids) {
			return [];
		}
		$rows = $this->rows($this->db->prepare(
			'SELECT work_id, translation_status, COUNT(*) AS count, MAX(published_at) AS latest FROM %i WHERE is_published = 1 AND work_id IN (' . implode(',', array_fill(0, count($ids), '%d')) . ') GROUP BY work_id, translation_status',
			$this->tables->name('chapters'), ...$ids
		));
		$result = [];
		foreach ($rows as $row) {
			$result[(int) $row['work_id']][] = $row;
		}
		return $result;
	}

	private function to_dto(\WP_Post $work, array $stats): array
	{
		$summary = ['total' => 0, 'untranslated' => 0, 'in_progress' => 0, 'completed' => 0, 'needs_review' => 0];
		$latest = null;
		foreach ($stats as $stat) {
			$summary['total'] += (int) $stat['count'];
			if (array_key_exists($stat['translation_status'], $summary)) {
				$summary[$stat['translation_status']] = (int) $stat['count'];
			}
			if ($stat['latest'] !== null && ($latest === null || $stat['latest'] > $latest)) {
				$latest = $stat['latest'];
			}
		}
		$terms = static function (string $taxonomy) use ($work): array {
			$values = get_the_terms($work, $taxonomy);
			return !$values || is_wp_error($values) ? [] : array_values(array_map(static fn ($term) => $term->slug, $values));
		};
		return [
			'id' => (int) $work->ID, 'slug' => $work->post_name, 'title' => $work->post_title,
			'type' => $terms('mol_work_type')[0] ?? 'other', 'genres' => $terms('mol_genre'),
			'source_language' => $terms('mol_source_language')[0] ?? '', 'work_status' => $terms('mol_work_status')[0] ?? '',
			'cover' => ImageResource::from_attachment((int) get_post_thumbnail_id($work)),
			'translation_summary' => $summary,
			'latest_published_chapter_at' => $latest === null ? null : (new \DateTimeImmutable($latest, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
			'read_count' => null,
		];
	}
}
