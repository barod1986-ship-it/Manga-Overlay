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
}
