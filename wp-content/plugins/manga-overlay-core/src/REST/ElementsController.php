<?php
declare(strict_types=1);

namespace MOL\REST;

use MOL\Security\Access;
use MOL\Services\ElementService;

final class ElementsController
{
	public function __construct(private readonly ElementService $service)
	{
	}

	public function register(): void
	{
		$this->route('/elements', 'POST', 'mol_edit_translations', function ($request) {
			$result = $this->service->create(ContentController::body($request), (string) $request->get_header('MOL-Idempotency-Key'));
			$response = new \WP_REST_Response($result, 201);
			$response->header('ETag', '"' . $result['data']['version'] . '"');
			return $response;
		});
		foreach (['PATCH', 'DELETE'] as $method) {
			$this->route('/elements/(?P<id>[1-9][0-9]*)', $method, $method === 'PATCH' ? 'mol_edit_translations' : 'mol_delete_translation_elements', function ($request) use ($method) {
				$result = $this->service->change((int) $request['id'], $method === 'PATCH' ? ContentController::body($request) : null, (string) $request->get_header('If-Match'), (string) $request->get_header('X-MOL-Lock-Token'), $method === 'DELETE');
				$response = $result === null ? new \WP_REST_Response(null, 204) : ContentController::response($result);
				if ($result !== null) {
					$response->header('ETag', '"' . $result['version'] . '"');
				}
				return $response;
			});
		}
		foreach (['POST', 'PUT', 'DELETE'] as $method) {
			$this->route('/elements/(?P<id>[1-9][0-9]*)/lock', $method, $method === 'DELETE' ? 'authenticated_user' : 'mol_edit_translations', function ($request) use ($method) {
				$result = $this->service->lease((int) $request['id'], $method, (string) $request->get_header('X-MOL-Lock-Token'));
				return $result === null ? new \WP_REST_Response(null, 204) : ContentController::response($result);
			});
		}
	}

	private function route(string $path, string $method, string $capability, callable $callback): void
	{
		register_rest_route('mol/v1', $path, [
			'methods' => $method,
			'permission_callback' => static fn ($request) => ContentController::safely(static fn () => Access::require($request, $capability)),
			'callback' => static fn ($request) => ContentController::safely(static fn () => $callback($request)),
		]);
	}
}
