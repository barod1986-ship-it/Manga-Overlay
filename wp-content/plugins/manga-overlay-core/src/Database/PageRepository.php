<?php
declare(strict_types=1);

namespace MOL\Database;

use MOL\Domain\Fault;
use MOL\Media\ImageResource;

final class PageRepository extends Repository
{
	public function find(int $id): ?array
	{
		$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE id = %d', $this->tables->name('pages'), $id));
		return $rows[0] ?? null;
	}

	public function has_attachment(int $id): bool
	{
		return (bool) $this->rows($this->db->prepare('SELECT id FROM %i WHERE attachment_id = %d LIMIT 1', $this->tables->name('pages'), $id));
	}

	public function for_chapter(int $id, bool $lock = false): array
	{
		return $this->rows($this->db->prepare('SELECT * FROM %i WHERE chapter_id = %d ORDER BY page_index, id' . ($lock ? ' FOR UPDATE' : ''), $this->tables->name('pages'), $id));
	}

	public function create(int $chapter_id, int $attachment_id, int $width, int $height): array
	{
		$pages = $this->for_chapter($chapter_id, true);
		$index = $pages ? max(array_column($pages, 'page_index')) + 1 : 0;
		$this->checked($this->db->insert($this->tables->name('pages'), [
			'chapter_id' => $chapter_id, 'page_index' => $index, 'attachment_id' => $attachment_id,
			'natural_width' => $width, 'natural_height' => $height, 'created_at' => current_time('mysql', true),
		]));
		return $this->find((int) $this->db->insert_id);
	}

	/** Caller owns the chapter transaction/lock. Never perform a direct unique-index swap. */
	public function reorder(int $chapter_id, array $ids): void
	{
		$rows = $this->for_chapter($chapter_id, true);
		$current = array_map('intval', array_column($rows, 'id'));
		$provided = $ids;
		sort($current);
		sort($provided);
		if (!$ids || $current !== $provided) {
			throw new Fault('mol_invalid_reorder', 'أرسل كل صفحات الفصل مرة واحدة بالترتيب المطلوب.', 400);
		}
		$max = (int) max(array_column($rows, 'page_index'));
		$offset = $max + count($ids) + 1;
		if ($max + $offset > 4294967295) {
			throw new Fault('mol_invalid_reorder', 'تجاوز ترتيب الصفحات نطاق الفهرس المسموح.', 400);
		}
		$moved = $this->checked($this->db->query($this->db->prepare('UPDATE %i SET page_index = page_index + %d WHERE chapter_id = %d', $this->tables->name('pages'), $offset, $chapter_id)));
		$cases = [];
		$arguments = [$this->tables->name('pages')];
		foreach ($ids as $index => $id) {
			$cases[] = 'WHEN %d THEN %d';
			array_push($arguments, $id, $index);
		}
		$arguments[] = $chapter_id;
		$changed = $this->checked($this->db->query($this->db->prepare('UPDATE %i SET page_index = CASE id ' . implode(' ', $cases) . ' END WHERE chapter_id = %d', ...$arguments)));
		if ($moved !== count($ids) || $changed !== count($ids)) {
			throw new \RuntimeException('Page reorder did not update every row.');
		}
	}

	public function delete(int $id): void
	{
		$this->checked($this->db->query($this->db->prepare('DELETE l FROM %i l INNER JOIN %i e ON e.id = l.element_id WHERE e.page_id = %d', $this->tables->name('element_locks'), $this->tables->name('elements'), $id)));
		$this->checked($this->db->query($this->db->prepare('DELETE c FROM %i c INNER JOIN %i e ON e.id = c.element_id WHERE e.page_id = %d', $this->tables->name('contributions'), $this->tables->name('elements'), $id)));
		$this->checked($this->db->query($this->db->prepare('UPDATE %i r LEFT JOIN %i e ON e.id = r.element_id SET r.page_id = NULL, r.element_id = NULL WHERE r.page_id = %d OR e.page_id = %d', $this->tables->name('reports'), $this->tables->name('elements'), $id, $id)));
		$this->checked($this->db->delete($this->tables->name('elements'), ['page_id' => $id]));
		$this->checked($this->db->delete($this->tables->name('pages'), ['id' => $id]));
	}

	public function delete_chapter(int $id): void
	{
		foreach ($this->for_chapter($id, true) as $page) {
			$this->delete((int) $page['id']);
		}
		foreach (['reports', 'reading_progress', 'contributions'] as $name) {
			$this->checked($this->db->delete($this->tables->name($name), ['chapter_id' => $id]));
		}
		$this->checked($this->db->delete($this->tables->name('chapters'), ['id' => $id]));
	}

	public function clamp_progress(int $chapter_id, int $last_index): void
	{
		$this->checked($this->db->query($this->db->prepare('UPDATE %i SET page_index = LEAST(page_index, %d) WHERE chapter_id = %d', $this->tables->name('reading_progress'), max(0, $last_index), $chapter_id)));
	}

	public static function to_dto(array $row): array
	{
		return [
			'id' => (int) $row['id'], 'chapter_id' => (int) $row['chapter_id'], 'page_index' => (int) $row['page_index'],
			'natural_width' => (int) $row['natural_width'], 'natural_height' => (int) $row['natural_height'],
			'image' => ImageResource::from_attachment((int) $row['attachment_id'], (int) $row['natural_width'], (int) $row['natural_height']),
		];
	}
}
