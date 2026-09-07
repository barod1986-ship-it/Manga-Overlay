<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Database\RateLimitRepository;
use MOL\Domain\Fault;

final class RateLimiter
{
	public function __construct(private readonly RateLimitRepository $repository)
	{
	}

	public function upload(): void
	{
		$limit = max(1, (int) get_option('mol_uploads_per_minute', 60));
		$result = $this->repository->increment('upload', get_current_user_id(), 60);
		if ($result['count'] > $limit) {
			throw new Fault('mol_rate_limited', 'وصلت إلى حد الرفع المؤقت. انتظر قليلًا ثم أعد المحاولة.', 429, ['retry_after' => $result['retry_after']]);
		}
	}

	public function element(): void
	{
		$this->limit('element_write', 'mol_element_writes_per_minute', 180);
	}

	public function lock(): void
	{
		$this->limit('lock_acquire', 'mol_lock_acquires_per_minute', 120);
	}

	private function limit(string $scope, string $option, int $default): void
	{
		$result = $this->repository->increment($scope, get_current_user_id(), 60);
		if ($result['count'] > max(1, (int) get_option($option, $default))) {
			throw new Fault('mol_rate_limited', 'طلبات كثيرة. انتظر قليلًا ثم أعد المحاولة.', 429, ['retry_after' => $result['retry_after']]);
		}
	}

}
