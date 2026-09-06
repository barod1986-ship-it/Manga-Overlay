<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\ChapterRepository;
use MOL\Database\OverlayReadRepository;
use MOL\Database\PageRepository;
use MOL\Security\ChapterVisibilityPolicy;

/** Server-rendered public reads never inherit draft visibility from an admin session. */
final class PublicReader
{
	private readonly ChapterRepository $chapters;
	private readonly PageRepository $pages;
	private readonly OverlayReadRepository $overlays;

	public function __construct(\wpdb $db)
	{
		$this->chapters = new ChapterRepository($db);
		$this->pages = new PageRepository($db);
		$this->overlays = new OverlayReadRepository($db);
	}

	public function chapter(int $id): array
	{
		return ChapterVisibilityPolicy::require($this->chapters->find($id));
	}

	public function work_chapters(int $work_id, array $args = []): array
	{
		if (get_post_type($work_id) !== 'mol_work' || get_post_status($work_id) !== 'publish') {
			return [];
		}
		$items = $this->chapters->for_work($work_id);
		if (isset($args['per_page'])) {
			$size = max(1, min(100, (int) $args['per_page']));
			$items = array_slice($items, (max(1, (int) ($args['page'] ?? 1)) - 1) * $size, $size);
		}
		return $items;
	}

	public function pages(int $id): array
	{
		$this->chapter($id);
		return array_map([PageRepository::class, 'to_dto'], $this->pages->for_chapter($id));
	}

	public function page_elements(int $id, string $lang): array
	{
		$page = $this->pages->find($id);
		if (!$page) {
			return [];
		}
		$this->chapter((int) $page['chapter_id']);
		return $this->overlays->for_page($id, $lang);
	}

	public function chapter_elements(int $id, string $lang): array
	{
		$this->chapter($id);
		$grouped = $this->overlays->for_chapter($id, $lang);
		return array_map(static fn ($page) => ['page_id' => (int) $page['id'], 'page_index' => (int) $page['page_index'], 'elements' => $grouped[(int) $page['id']] ?? []], $this->pages->for_chapter($id));
	}

	public function contributors(int $id): array
	{
		$this->chapter($id);
		return $this->overlays->contributors($id);
	}
}
