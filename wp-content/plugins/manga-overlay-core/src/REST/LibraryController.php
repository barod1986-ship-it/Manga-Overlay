<?php
declare(strict_types=1);

namespace MOL\REST;

use MOL\Database\ProfileRepository;
use MOL\Database\WorkRepository;
use MOL\Domain\LibraryInput;

final class LibraryController
{
	public function __construct(private readonly WorkRepository $works, private readonly ProfileRepository $profiles)
	{
	}

	public function register(): void
	{
		foreach ([
			'/library' => function ($request) {
				$result = $this->works->library(LibraryInput::validate($request->get_query_params()));
				return ContentController::response($result['data'], $result['meta']);
			},
			'/works/(?P<id>[1-9][0-9]*)' => fn ($request) => ContentController::response($this->works->detail((int) $request['id'])),
			'/profiles/(?P<username>[^/]+)' => fn ($request) => ContentController::response($this->profiles->find(rawurldecode($request['username']))),
		] as $route => $callback) {
			register_rest_route('mol/v1', $route, ['methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => static fn ($request) => ContentController::safely(static fn () => $callback($request))]);
		}
	}
}
