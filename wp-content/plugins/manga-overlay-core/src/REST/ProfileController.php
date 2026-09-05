<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Repositories\ContributionRepository;

final class ProfileController
{
    public function __construct(private readonly ContributionRepository $contributions)
    {
    }

    public function get(\WP_REST_Request $request): mixed
    {
        return Responses::execute(function () use ($request): \WP_REST_Response {
            $username = PublicRequestValidator::username($request['username']);
            $user = get_user_by('login', $username);
            if (! $user instanceof \WP_User) {
                throw ApiException::notFound();
            }
            $summary = $this->contributions->publicProfileSummary((int) $user->ID);
            $response = new \WP_REST_Response(array(
                'data' => ProfilePresenter::one($user, $summary),
                'meta' => (object) array(),
            ));

            return CacheHeaders::etag(CacheHeaders::publicResponse($response), $response->get_data());
        });
    }
}
