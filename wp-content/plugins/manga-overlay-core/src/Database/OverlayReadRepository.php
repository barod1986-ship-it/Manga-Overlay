<?php
declare(strict_types=1);

namespace MOL\Database;

final class OverlayReadRepository extends Repository
{
	public function for_page(int $id, string $lang): array
	{
		return array_map([self::class, 'to_dto'], $this->rows($this->db->prepare('SELECT * FROM %i WHERE page_id = %d AND target_lang = %s ORDER BY z_index, id', $this->tables->name('elements'), $id, $lang)));
	}

	/** One query for the chapter, not one query per element or page. */
	public function for_chapter(int $id, string $lang): array
	{
		$rows = $this->rows($this->db->prepare('SELECT e.* FROM %i e INNER JOIN %i p ON p.id = e.page_id WHERE p.chapter_id = %d AND e.target_lang = %s ORDER BY p.page_index, e.z_index, e.id', $this->tables->name('elements'), $this->tables->name('pages'), $id, $lang));
		$grouped = [];
		foreach ($rows as $row) {
			$grouped[(int) $row['page_id']][] = self::to_dto($row);
		}
		return $grouped;
	}

	public function contributors(int $id): array
	{
		$rows = $this->rows($this->db->prepare('SELECT user_id, COUNT(DISTINCT element_id) AS element_count FROM %i WHERE chapter_id = %d GROUP BY user_id ORDER BY element_count DESC, user_id', $this->tables->name('contributions'), $id));
		return array_map(static function (array $row): array {
			$user = get_userdata((int) $row['user_id']);
			return [
				'user_id' => (int) $row['user_id'], 'username' => $user ? $user->user_nicename : '',
				'display_name' => $user ? $user->display_name : 'مستخدم محذوف',
				'avatar_url' => $user ? (get_avatar_url($user->ID) ?: null) : null,
				'profile_tag' => $user ? (get_user_meta($user->ID, 'mol_profile_tag', true) ?: null) : null,
				'element_count' => (int) $row['element_count'],
			];
		}, $rows);
	}

	public static function to_dto(array $row): array
	{
		$result = [];
		foreach (['id', 'page_id', 'x_unit', 'y_unit', 'w_unit', 'h_unit', 'rotation_mdeg', 'z_index', 'version', 'created_by', 'updated_by'] as $key) {
			$result[$key] = (int) $row[$key];
		}
		foreach (['target_lang', 'element_type', 'content'] as $key) {
			$result[$key] = (string) $row[$key];
		}
		$result['style'] = json_decode($row['style_json'], false, 512, JSON_THROW_ON_ERROR);
		foreach (['created_at', 'updated_at'] as $key) {
			$result[$key] = (new \DateTimeImmutable($row[$key], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
		}
		return $result;
	}
}
