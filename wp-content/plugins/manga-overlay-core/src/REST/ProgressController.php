<?php
declare(strict_types=1);

namespace MOL\REST;

use MOL\Security\Access;
use MOL\Services\ProgressService;

final class ProgressController
{
	public function __construct(private readonly ProgressService $service)
	{
	}

	public function register(): void
	{
		register_rest_route('mol/v1', '/reading-progress', [
			'methods' => 'PUT',
			'permission_callback' => static fn ($request) => ContentController::safely(static fn () => Access::require($request, 'authenticated_user')),
			'callback' => fn ($request) => ContentController::safely(fn () => ContentController::response($this->service->save(ContentController::body($request)))),
		]);
	}
}
