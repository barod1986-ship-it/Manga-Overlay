<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\ElementWriteService;

final class ElementController
{
    public function __construct(private readonly ElementWriteService $writes)
    {
    }

    public function create(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $element = $this->writes->create(
                get_current_user_id(),
                (string) $request->get_header('MOL-Idempotency-Key'),
                RequestValidator::elementCreate($request->get_json_params())
            );

            return $this->elementResponse($element, 201);
        });
    }

    public function update(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $element = $this->writes->update(
                $this->id($request),
                get_current_user_id(),
                (string) $request->get_header('If-Match'),
                (string) $request->get_header('X-MOL-Lock-Token'),
                RequestValidator::elementPatch($request->get_json_params())
            );

            return $this->elementResponse($element);
        });
    }

    public function delete(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $this->writes->delete(
                $this->id($request),
                get_current_user_id(),
                (string) $request->get_header('If-Match'),
                (string) $request->get_header('X-MOL-Lock-Token')
            );

            return CacheHeaders::privateResponse(new \WP_REST_Response(null, 204));
        });
    }

    /** @param array<string, mixed> $element */
    private function elementResponse(array $element, int $status = 200): \WP_REST_Response
    {
        $response = new \WP_REST_Response(array(
            'data' => ElementPresenter::one($element),
            'meta' => (object) array(),
        ), $status);
        $response->header('ETag', sprintf('"%d"', (int) $element['version']));

        return CacheHeaders::privateResponse($response);
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
