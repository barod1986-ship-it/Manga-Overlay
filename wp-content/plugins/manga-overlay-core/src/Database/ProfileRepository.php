<?php
declare(strict_types=1);

namespace MOL\Database;

use MOL\Domain\Fault;

final class ProfileRepository extends Repository
{
	public function find(string $username): array
	{
		$user = get_user_by('slug', $username);
		if (!$user) {
			throw Fault::missing();
		}
		$from = $this->db->prepare(
			' FROM %i c INNER JOIN %i ch ON ch.id = c.chapter_id INNER JOIN %i w ON w.ID = ch.work_id WHERE c.user_id = %d AND ch.is_published = 1 AND w.post_type = %s AND w.post_status = %s',
			$this->tables->name('contributions'), $this->tables->name('chapters'), $this->db->posts, $user->ID, 'mol_work', 'publish'
		);
		$stats = $this->rows('SELECT COUNT(DISTINCT ch.work_id) AS works, COUNT(DISTINCT ch.id) AS chapters, COUNT(DISTINCT c.element_id) AS elements' . $from)[0];
		$rows = $this->rows('SELECT ch.work_id, w.post_title AS work_title, ch.id AS chapter_id, ch.chapter_label, COUNT(DISTINCT c.element_id) AS element_count, MAX(c.last_contributed_at) AS last_contributed_at' . $from . ' GROUP BY ch.work_id, w.post_title, ch.id, ch.chapter_label ORDER BY last_contributed_at DESC, chapter_id DESC LIMIT 20');
		$recent = array_map(static fn ($row) => [
			'work_id' => (int) $row['work_id'], 'work_title' => $row['work_title'], 'chapter_id' => (int) $row['chapter_id'],
			'chapter_label' => $row['chapter_label'], 'element_count' => (int) $row['element_count'],
			'last_contributed_at' => (new \DateTimeImmutable($row['last_contributed_at'], new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
		], $rows);
		return [
			'username' => $user->user_nicename, 'display_name' => $user->display_name, 'bio' => (string) get_user_meta($user->ID, 'description', true),
			'profile_tag' => get_user_meta($user->ID, 'mol_profile_tag', true) ?: null, 'avatar_url' => get_avatar_url($user->ID) ?: null,
			'joined_at' => (new \DateTimeImmutable($user->user_registered, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
			'stats' => array_map('intval', $stats), 'recent_contributions' => $recent,
		];
	}
}
