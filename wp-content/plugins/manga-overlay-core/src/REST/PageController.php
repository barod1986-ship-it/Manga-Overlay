<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\ContentDeletionService;
use MOL\Services\PageReorderService;
use MOL\Services\PageUploadService;

final class PageController
{
    public function __construct(
        private readonly PageUploadService $uploads,
        private readonly PageReorderService $reorders,
        private readonly ContentDeletionService $deletions
    ) {
    }

    public function upload(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $files = $request->get_file_params();
            if (array('image') !== array_keys($files) || ! is_array($files['image'])) {
                throw ApiException::invalidParams('Exactly one image file is required.');
            }
            if (array() !== $request->get_body_params()) {
                throw ApiException::invalidParams('The upload contains unsupported form properties.');
            }
            $page = $this->uploads->upload(
                $this->id($request),
                get_current_user_id(),
                $request->get_header('MOL-Idempotency-Key'),
                $files['image']
            );

            return new \WP_REST_Response(array(
                'data' => PagePresenter::one($page),
                'meta' => (object) array(),
            ), 201);
        });
    }

    public function reorder(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $chapterId = $this->id($request);
            $pageIds = RequestValidator::pageOrder($request->get_json_params());
            $pages = $this->reorders->reorder($chapterId, $pageIds);

            return new \WP_REST_Response(array(
                'data' => PagePresenter::many($pages),
                'meta' => array('chapter_id' => $chapterId, 'count' => count($pages)),
            ));
        });
    }

    public function delete(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            if (! $this->deletions->deletePage($this->id($request))) {
                throw ApiException::notFound('Page not found.');
            }

            return new \WP_REST_Response(null, 204);
        });
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
