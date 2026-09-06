<?php
declare(strict_types=1);

namespace MOL\Database;

final class IdempotencyRepository extends Repository
{
	public function acquire(string $identity): string
	{
		$name = 'mol_retry_' . substr(hash('sha256', DB_NAME . ':' . $this->db->prefix . ':' . $identity), 0, 48);
		$acquired = $this->db->get_var($this->db->prepare('SELECT GET_LOCK(%s, 30)', $name));
		if ((string) $acquired !== '1') {
			throw new \RuntimeException('Could not serialize the retry key.');
		}
		return $name;
	}

	public function release(string $name): void
	{
		$this->db->get_var($this->db->prepare('SELECT RELEASE_LOCK(%s)', $name));
	}

	public function find(int $user_id, string $scope, string $key): ?array
	{
		$rows = $this->rows($this->db->prepare('SELECT * FROM %i WHERE user_id = %d AND scope = %s AND idempotency_key = %s', $this->tables->name('idempotency_keys'), $user_id, $scope, $key));
		return $rows[0] ?? null;
	}

	public function save(int $user_id, string $scope, string $key, string $hash, array $response): void
	{
		$this->checked($this->db->insert($this->tables->name('idempotency_keys'), [
			'user_id' => $user_id, 'scope' => $scope, 'idempotency_key' => $key, 'request_hash' => $hash,
			'resource_type' => 'page', 'resource_id' => $response['data']['id'], 'response_code' => 201,
			'response_json' => wp_json_encode($response, JSON_THROW_ON_ERROR),
			'created_at' => current_time('mysql', true), 'expires_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
		]));
	}

	public function delete(int $id): void
	{
		$this->checked($this->db->delete($this->tables->name('idempotency_keys'), ['id' => $id]));
	}

	public function cleanup(): void
	{
		$this->checked($this->db->query($this->db->prepare('DELETE FROM %i WHERE expires_at < %s', $this->tables->name('idempotency_keys'), current_time('mysql', true))));
	}
}
