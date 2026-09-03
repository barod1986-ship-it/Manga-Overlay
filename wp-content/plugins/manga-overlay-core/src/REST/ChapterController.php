<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Services\ChapterService;
use MOL\Services\ContentDeletionService;

final class ChapterController
{
    public function __construct(
        private readonly ChapterService $chapters,
        private readonly ContentDeletionService $deletions
    ) {
    }

    public function create(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $payload = RequestValidator::chapterCreate($request->get_json_params());
            $chapter = $this->chapters->create($payload, get_current_user_id());

            return $this->chapterResponse($chapter, 201);
        });
    }

    public function update(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $chapterId = $this->id($request);
            $changes = RequestValidator::chapterPatch($request->get_json_params());

            return $this->chapterResponse($this->chapters->update($chapterId, $changes));
        });
    }

    public function delete(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            if (! $this->deletions->deleteChapter($this->id($request))) {
                throw ApiException::notFound('Chapter not found.');
            }

            return new \WP_REST_Response(null, 204);
        });
    }

    public function review(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $status = RequestValidator::chapterReview($request->get_json_params());

            return $this->chapterResponse($this->chapters->review($this->id($request), $status));
        });
    }

    /** @param array<string, mixed> $chapter */
    private function chapterResponse(array $chapter, int $status = 200): \WP_REST_Response
    {
        return new \WP_REST_Response(array(
            'data' => ChapterPresenter::one($chapter),
            'meta' => (object) array(),
        ), $status);
    }

    private function id(\WP_REST_Request $request): int
    {
        $chapterId = (int) $request['id'];
        if ($chapterId < 1) {
            throw ApiException::invalidParams('id must be a positive integer.');
        }

        return $chapterId;
    }
}
