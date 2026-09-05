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
            return $this->leaseResponse($this->locks->acquire($this->id($request), get_current_user_id()));
        });
    }

    public function renew(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            return $this->leaseResponse($this->locks->renew(
                $this->id($request),
                get_current_user_id(),
                (string) $request->get_header('X-MOL-Lock-Token')
            ));
        });
    }

    public function release(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $this->locks->release(
                $this->id($request),
                get_current_user_id(),
                (string) $request->get_header('X-MOL-Lock-Token')
            );

            return CacheHeaders::privateResponse(new \WP_REST_Response(null, 204));
        });
    }

    /** @param array{element_id: int, user_id: int, lock_token: string, expires_at: string} $lease */
    private function leaseResponse(array $lease): \WP_REST_Response
    {
        $lease['expires_at'] = PresenterSupport::dateTime($lease['expires_at']) ?? '';

        return CacheHeaders::privateResponse(new \WP_REST_Response(array(
            'data' => $lease,
            'meta' => (object) array(),
        )));
    }

    private function id(\WP_REST_Request $request): int
    {
        $id = (int) $request['id'];
        if ($id < 1) {
            throw ApiException::invalidParams('id must be a positive integer.');
        }

        return $id;
    }
}
