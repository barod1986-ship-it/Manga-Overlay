<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\ElementLockService;

final class ElementLockController
{
    public function __construct(private readonly ElementLockService $locks)
    {
    }

    public function acquire(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $id = (int) $request['id'];
            if ($id < 1) {
                throw ApiException::invalidParams('id must be a positive integer.');
            }
            $lease = $this->locks->acquire($id, get_current_user_id());
            $lease['expires_at'] = PresenterSupport::dateTime($lease['expires_at']) ?? '';

            return CacheHeaders::privateResponse(new \WP_REST_Response(array(
                'data' => $lease,
                'meta' => (object) array(),
            )));
        });
    }
}
