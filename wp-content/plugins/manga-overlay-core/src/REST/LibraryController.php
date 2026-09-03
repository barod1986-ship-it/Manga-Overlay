<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Repositories\ChapterRepository;
use MOL\Repositories\WorkRepository;

final class LibraryController
{
    public function __construct(
        private readonly WorkRepository $works,
        private readonly ChapterRepository $chapters
    ) {
    }

    public function listWorks(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $filters = PublicRequestValidator::library($request->get_query_params());
            $result = $this->works->search($filters);
            $workIds = array_map(
                static fn (\WP_Post $work): int => (int) $work->ID,
                $result['items']
            );
            $summaries = $this->works->chapterSummaries($workIds);
            $data = array_map(
                static fn (\WP_Post $work): array => WorkPresenter::summary(
                    $work,
                    $summaries[(int) $work->ID] ?? array()
                ),
                $result['items']
            );
            $response = new \WP_REST_Response(array(
                'data' => $data,
                'meta' => array(
                    'page' => $filters['page'],
                    'per_page' => $filters['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                    'sort' => $filters['sort'],
                    'most_read_available' => false,
                ),
            ));

            return CacheHeaders::etag(CacheHeaders::publicResponse($response), $response->get_data());
        });
    }

    public function getWork(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $workId = PublicRequestValidator::id($request['id']);
            $work = $this->works->findPublished($workId);
            if (null === $work) {
                throw ApiException::notFound();
            }
            $summaries = $this->works->chapterSummaries(array($workId));
            $response = new \WP_REST_Response(array(
                'data' => WorkPresenter::detail($work, $summaries[$workId] ?? array()),
                'meta' => (object) array(),
            ));

            return CacheHeaders::etag(CacheHeaders::publicResponse($response), $response->get_data());
        });
    }

    public function listWorkChapters(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $workId = PublicRequestValidator::id($request['id']);
            if (null === $this->works->findPublished($workId)) {
                throw ApiException::notFound();
            }
            $pagination = PublicRequestValidator::pagination($request->get_query_params());
            $result = $this->chapters->forWorkPaginated(
                $workId,
                $pagination['page'],
                $pagination['per_page'],
                true
            );
            $response = new \WP_REST_Response(array(
                'data' => ChapterPresenter::many($result['items']),
                'meta' => array(
                    'page' => $pagination['page'],
                    'per_page' => $pagination['per_page'],
                    'total' => $result['total'],
                    'total_pages' => $result['total_pages'],
                ),
            ));

            return CacheHeaders::etag(CacheHeaders::publicResponse($response), $response->get_data());
        });
    }
}
