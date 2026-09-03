<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\MediaService;

final class CapabilitiesController
{
    public function __construct(private readonly MediaService $media)
    {
    }

    public function get(\WP_REST_Request $request): mixed
    {
        unset($request);

        return Responses::execute(function (): \WP_REST_Response {
            $response = new \WP_REST_Response(array(
                'data' => $this->media->runtimeCapabilities(),
                'meta' => (object) array(),
            ));

            return CacheHeaders::etag(CacheHeaders::publicResponse($response, 300), $response->get_data());
        });
    }
}
