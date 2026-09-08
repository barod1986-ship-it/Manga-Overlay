<?php
declare(strict_types=1);

namespace MOL\REST;

use MOL\Security\Access;
use MOL\Services\ReportService;

final class ReportsController
{
	public function __construct(private readonly ReportService $service) {}
	public function register(): void
	{
		$this->route('/reports', 'POST', 'mol_report_issue', fn ($request) => ContentController::response($this->service->create(ContentController::body($request), $request), [], 201));
		$this->route('/reports', 'GET', 'mol_moderate_reports', function ($request) {
			$result = $this->service->listing($request->get_query_params());
			return ContentController::response($result['data'], $result['meta']);
		});
		$this->route('/reports/(?P<id>[1-9][0-9]*)', 'PATCH', 'mol_moderate_reports', fn ($request) => ContentController::response($this->service->change((int) $request['id'], ContentController::body($request))));
	}
	private function route(string $path, string $method, string $capability, callable $callback): void
	{
		register_rest_route('mol/v1', $path, ['methods' => $method,
			'permission_callback' => static fn ($request) => ContentController::safely(static fn () => Access::require($request, $capability)),
			'callback' => static fn ($request) => ContentController::safely(static fn () => $callback($request)),
		]);
	}
}
