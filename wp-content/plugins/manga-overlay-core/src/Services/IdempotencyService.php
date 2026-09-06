<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\IdempotencyRepository;
use MOL\Database\Transaction;
use MOL\Domain\Fault;

final class IdempotencyService
{
	public function __construct(private readonly IdempotencyRepository $repository, private readonly Transaction $transaction)
	{
	}

	public function run(string $scope, string $key, string $hash, callable $operation): array
	{
		if ($key === '' || mb_strlen($key) > 100) {
			throw Fault::invalid('مفتاح إعادة المحاولة مطلوب، بحد أقصى 100 حرف.');
		}
		$user_id = get_current_user_id();
		// Preserve case-sensitive header identity on the canonical case-insensitive SQL collation.
		$key = hash('sha256', $key);
		$lock = $this->repository->acquire($user_id . ':' . $scope . ':' . $key);
		try {
			return $this->transaction->run(function () use ($user_id, $scope, $key, $hash, $operation): array {
				$previous = $this->repository->find($user_id, $scope, $key);
				if ($previous && $previous['expires_at'] <= current_time('mysql', true)) {
					$this->repository->delete((int) $previous['id']);
					$previous = null;
				}
				if ($previous) {
					if (!hash_equals($previous['request_hash'], $hash)) {
						throw new Fault('mol_idempotency_mismatch', 'استُخدم مفتاح إعادة المحاولة لطلب مختلف.', 409);
					}
					$response = json_decode($previous['response_json'], true, 512, JSON_THROW_ON_ERROR);
					$response['meta'] = (object) $response['meta'];
					return $response;
				}
				$response = $operation();
				$this->repository->save($user_id, $scope, $key, $hash, $response);
				return $response;
			});
		} finally {
			$this->repository->release($lock);
		}
	}
}
