<?php
declare(strict_types=1);

namespace MOL\REST;

use MOL\Security\Access;
use MOL\Services\PresetService;

final class PresetsController
{
	public function __construct(private readonly PresetService $service) {}
	public function register(): void
	{
		$this->route('/presets', 'GET', function ($request) {
			// Invalid filters match no rows: this frozen GET declares only 200/401/403.
			$work = $request->get_param('work_id'); $type = $request->get_param('type');
			$invalid = ($work !== null && (!is_scalar($work) || !preg_match('/^[1-9][0-9]*$/D', (string) $work))) || ($type !== null && !in_array($type, ['bubble','narration','free_text','sfx'], true));
			$data = $invalid ? [] : $this->service->available($work === null ? null : (int) $work, $type);
			return new \WP_REST_Response(['data' => $data, 'meta' => ['count' => count($data)]]);
		});
		$this->route('/presets', 'POST', fn ($request) => ContentController::response($this->service->create(ContentController::body($request)), [], 201));
		foreach (['PATCH','DELETE'] as $method) {
			$this->route('/presets/(?P<id>[1-9][0-9]*)', $method, function ($request) use ($method) {
				$data = $this->service->change((int) $request['id'], $method === 'PATCH' ? ContentController::body($request) : null, $method === 'DELETE');
				return $data === null ? new \WP_REST_Response(null, 204) : ContentController::response($data);
			});
		}
	}
	private function route(string $path, string $method, callable $callback): void
	{
		register_rest_route('mol/v1', $path, ['methods' => $method,
			'permission_callback' => static fn ($request) => ContentController::safely(static fn () => Access::require($request, 'mol_use_editor')),
			'callback' => static fn ($request) => ContentController::safely(static fn () => $callback($request)),
		]);
	}
}
