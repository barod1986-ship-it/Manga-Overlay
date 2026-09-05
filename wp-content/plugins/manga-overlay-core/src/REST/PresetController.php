<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\PresetService;

final class PresetController
{
    public function __construct(private readonly PresetService $presets)
    {
    }

    public function listPresets(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $query = RequestValidator::presetQuery($request->get_param('work_id'), $request->get_param('type'));
            $presets = $this->presets->available(
                get_current_user_id(),
                $query['work_id'],
                $query['element_type']
            );

            return CacheHeaders::privateResponse(new \WP_REST_Response(array(
                'data' => PresetPresenter::many($presets),
                'meta' => array('count' => count($presets)),
            )));
        });
    }

    public function create(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $preset = $this->presets->create(
                get_current_user_id(),
                RequestValidator::presetCreate($request->get_json_params())
            );

            return $this->response($preset, 201);
        });
    }

    public function update(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $preset = $this->presets->update(
                $this->id($request),
                get_current_user_id(),
                RequestValidator::presetPatch($request->get_json_params())
            );

            return $this->response($preset);
        });
    }

    public function delete(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $this->presets->delete($this->id($request), get_current_user_id());

            return CacheHeaders::privateResponse(new \WP_REST_Response(null, 204));
        });
    }

    /** @param array<string, mixed> $preset */
    private function response(array $preset, int $status = 200): \WP_REST_Response
    {
        return CacheHeaders::privateResponse(new \WP_REST_Response(array(
            'data' => PresetPresenter::one($preset),
            'meta' => (object) array(),
        ), $status));
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
