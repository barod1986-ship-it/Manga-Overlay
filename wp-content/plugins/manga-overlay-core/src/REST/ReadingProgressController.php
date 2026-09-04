<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\ReadingProgressService;

final class ReadingProgressController
{
    public function __construct(private readonly ReadingProgressService $progress)
    {
    }

    public function save(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $payload = RequestValidator::readingProgress($request->get_json_params());
            $progress = $this->progress->save(get_current_user_id(), $payload);

            return new \WP_REST_Response(array(
                'data' => ReadingProgressPresenter::one($progress),
                'meta' => (object) array(),
            ));
        });
    }
}
