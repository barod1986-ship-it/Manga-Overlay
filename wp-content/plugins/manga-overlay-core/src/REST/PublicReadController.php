<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\PublicReadService;

final class PublicReadController
{
    public function __construct(private readonly PublicReadService $reads)
    {
    }

    public function getChapter(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $result = $this->reads->chapter(PublicRequestValidator::id($request['id']));
            $response = new \WP_REST_Response(array(
                'data' => ChapterPresenter::one($result['chapter']),
                'meta' => (object) array(),
            ));

            return $this->cache($response, $result['public']);
        });
    }

    public function listChapterPages(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $chapterId = PublicRequestValidator::id($request['id']);
            $result = $this->reads->chapterPages($chapterId);
            $response = new \WP_REST_Response(array(
                'data' => PagePresenter::many($result['pages']),
                'meta' => array(
                    'chapter_id' => $chapterId,
                    'count' => count($result['pages']),
                ),
            ));

            return $this->cache($response, $result['public']);
        });
    }

    public function listPageElements(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $pageId = PublicRequestValidator::id($request['id']);
            $targetLanguage = PublicRequestValidator::language($request->get_param('lang'));
            $result = $this->reads->pageElements($pageId, $targetLanguage);
            $response = new \WP_REST_Response(array(
                'data' => ElementPresenter::many($result['elements']),
                'meta' => array(
                    'page_id' => $pageId,
                    'target_lang' => $targetLanguage,
                    'count' => count($result['elements']),
                ),
            ));

            return $this->cache($response, $result['public'], true);
        });
    }

    public function listChapterElements(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $chapterId = PublicRequestValidator::id($request['id']);
            $targetLanguage = PublicRequestValidator::language($request->get_param('lang'));
            $result = $this->reads->chapterElements($chapterId, $targetLanguage);
            $data = array_map(
                static fn (array $group): array => array(
                    'page_id' => (int) $group['page']['id'],
                    'page_index' => (int) $group['page']['page_index'],
                    'elements' => ElementPresenter::many($group['elements']),
                ),
                $result['pages']
            );
            $response = new \WP_REST_Response(array(
                'data' => $data,
                'meta' => array(
                    'chapter_id' => $chapterId,
                    'target_lang' => $targetLanguage,
                    'page_count' => count($data),
                    'element_count' => $result['element_count'],
                ),
            ));

            return $this->cache($response, $result['public'], true);
        });
    }

    public function listChapterContributors(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $chapterId = PublicRequestValidator::id($request['id']);
            $result = $this->reads->chapterContributors($chapterId);
            $contributors = ContributorPresenter::many($result['contributors']);
            $response = new \WP_REST_Response(array(
                'data' => $contributors,
                'meta' => array(
                    'chapter_id' => $chapterId,
                    'count' => count($contributors),
                ),
            ));

            return $this->cache($response, $result['public']);
        });
    }

    private function cache(
        \WP_REST_Response $response,
        bool $public,
        bool $withEtag = false
    ): \WP_REST_Response {
        $response = $public
            ? CacheHeaders::publicResponse($response)
            : CacheHeaders::privateResponse($response);

        return $withEtag ? CacheHeaders::etag($response, $response->get_data()) : $response;
    }
}
