<?php

declare(strict_types=1);

namespace MOL\REST;

final class CacheHeaders
{
    public static function publicResponse(\WP_REST_Response $response, int $maxAge = 60): \WP_REST_Response
    {
        $maxAge = max(0, $maxAge);
        $response->header('Cache-Control', sprintf('public, max-age=%d, s-maxage=%d', $maxAge, $maxAge * 5));

        return $response;
    }

    public static function privateResponse(\WP_REST_Response $response): \WP_REST_Response
    {
        $response->header('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    public static function etag(\WP_REST_Response $response, mixed $representation): \WP_REST_Response
    {
        $encoded = wp_json_encode($representation, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded)) {
            $response->header('ETag', '"' . hash('sha256', $encoded) . '"');
        }

        return $response;
    }
}
