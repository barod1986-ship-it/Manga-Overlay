<?php

declare(strict_types=1);

namespace MOL\Services;

final class RateLimiter
{
    public function consumeUpload(int $userId): ?int
    {
        $limit = max(0, (int) apply_filters('mol_upload_rate_limit', 20, $userId));
        $window = max(1, (int) apply_filters('mol_upload_rate_window', 60, $userId));
        if (0 === $limit) {
            return null;
        }

        $now = time();
        $key = 'mol_upload_rate_' . md5((string) $userId);
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
