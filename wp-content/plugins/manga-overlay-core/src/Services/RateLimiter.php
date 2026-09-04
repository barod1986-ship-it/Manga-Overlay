<?php

declare(strict_types=1);

namespace MOL\Services;

final class RateLimiter
{
    public function consumeUpload(int $userId): ?int
    {
        return $this->consume(
            'upload',
            $userId,
            max(0, (int) apply_filters('mol_upload_rate_limit', 20, $userId)),
            max(1, (int) apply_filters('mol_upload_rate_window', 60, $userId))
        );
    }

    public function consumeElementWrite(int $userId): ?int
    {
        return $this->consume(
            'element_write',
            $userId,
            max(0, (int) apply_filters('mol_element_write_rate_limit', 120, $userId)),
            max(1, (int) apply_filters('mol_element_write_rate_window', 60, $userId))
        );
    }

    public function consumeLockAcquire(int $userId): ?int
    {
        return $this->consume(
            'lock_acquire',
            $userId,
            max(0, (int) apply_filters('mol_lock_acquire_rate_limit', 60, $userId)),
            max(1, (int) apply_filters('mol_lock_acquire_rate_window', 60, $userId))
        );
    }

    private function consume(string $scope, int $userId, int $limit, int $window): ?int
    {
        if (0 === $limit) {
            return null;
        }

        $now = time();
        $key = 'mol_' . $scope . '_rate_' . md5((string) $userId);
        $record = get_transient($key);
        if (! is_array($record)
            || ! isset($record['count'], $record['reset'])
            || (int) $record['reset'] <= $now
        ) {
            $record = array('count' => 0, 'reset' => $now + $window);
        }

        if ((int) $record['count'] >= $limit) {
            return max(1, (int) $record['reset'] - $now);
        }

        ++$record['count'];
        set_transient($key, $record, max(1, (int) $record['reset'] - $now));

        return null;
    }
}
