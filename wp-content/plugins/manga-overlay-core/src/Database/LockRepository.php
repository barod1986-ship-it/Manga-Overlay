<?php
declare(strict_types=1);

namespace MOL\Database;

/** Call under the parent chapter and element row locks, in that order. */
final class LockRepository extends Repository
{
	public function find(int $id): ?array
	{
		return $this->rows($this->db->prepare('SELECT * FROM %i WHERE element_id = %d FOR UPDATE', $this->tables->name('element_locks'), $id))[0] ?? null;
	}

	public function save(int $id, int $user_id, string $token, bool $renew = false): array
	{
		$data = ['element_id' => $id, 'user_id' => $user_id, 'lock_token' => $token, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 45)];
		if ($renew) {
			$this->checked($this->db->update($this->tables->name('element_locks'), ['expires_at' => $data['expires_at']], ['element_id' => $id]));
		} else {
			$data['acquired_at'] = current_time('mysql', true);
			$this->checked($this->db->replace($this->tables->name('element_locks'), $data));
		}
		return ['element_id' => $id, 'user_id' => $user_id, 'lock_token' => $token, 'expires_at' => str_replace(' ', 'T', $data['expires_at']) . 'Z'];
	}

	public function delete(int $id): void
	{
		$this->checked($this->db->delete($this->tables->name('element_locks'), ['element_id' => $id]));
	}
}
