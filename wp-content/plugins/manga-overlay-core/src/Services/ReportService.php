<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\{ChapterRepository, ElementRepository, PageRepository, RateLimitRepository, ReportRepository, Transaction};
use MOL\Domain\{ElementInput, Fault};
use MOL\Security\{Access, ChapterVisibilityPolicy, RateLimiter};

final class ReportService
{
	public const STATUSES = ['open', 'in_review', 'resolved', 'rejected'];
	private readonly ReportRepository $reports;
	private readonly Transaction $transaction;
	public function __construct(private readonly \wpdb $db)
	{
		$this->reports = new ReportRepository($db); $this->transaction = new Transaction($db);
	}

	public function create(mixed $body, \WP_REST_Request $request): array
	{
		Access::require($request, 'mol_report_issue');
		(new RateLimiter(new RateLimitRepository($this->db)))->report();
		$data = ElementInput::validate('ReportCreate', $body);
		$message = trim(sanitize_textarea_field($data['message']));
		if ($message === '') { throw Fault::invalid('اكتب وصفًا للمشكلة.'); }
		$elements = new ElementRepository($this->db); $pages = new PageRepository($this->db);
		// Resolve the optional page hint outside the transaction, then lock and re-read every parent.
		$hint = isset($data['element_id']) ? $elements->find((int) $data['element_id']) : null;
		$page_id = $data['page_id'] ?? $hint['page_id'] ?? null;
		return $this->transaction->run(function () use ($data, $request, $message, $page_id, $elements, $pages): array {
			$chapter = (new ChapterRepository($this->db))->lock((int) $data['chapter_id']);
			if ($chapter) { clean_post_cache($chapter['work_id']); }
			try { ChapterVisibilityPolicy::require($chapter, $request); }
			catch (Fault $error) { throw Fault::invalid('موضع البلاغ غير متاح.'); }
			$page = $page_id !== null ? $pages->find((int) $page_id, true) : null;
			if ($page_id !== null && (!$page || (int) $page['chapter_id'] !== $chapter['id'])) { throw Fault::invalid('موضع البلاغ غير متاح.'); }
			if (isset($data['element_id'])) {
				$element = $elements->find((int) $data['element_id'], true);
				if (!$element || !$page || $element['page_id'] !== (int) $page['id']) { throw Fault::invalid('موضع البلاغ غير متاح.'); }
			}
			// Authority is derived from the current session; clients cannot set ownership or resolution.
			Access::require($request, 'mol_report_issue');
			return $this->reports->save(null, ['chapter_id' => $chapter['id'], 'page_id' => $page ? (int) $page['id'] : null,
				'element_id' => isset($data['element_id']) ? (int) $data['element_id'] : null, 'reporter_id' => get_current_user_id(),
				'report_type' => $data['report_type'], 'message' => $message, 'status' => 'open', 'resolved_by' => null, 'resolved_at' => null, 'created_at' => current_time('mysql', true)]);
		});
	}

	public function listing(array $query): array
	{
		Access::capability('mol_moderate_reports');
		$filters = ['page' => 1, 'per_page' => 20]; $invalid = false;
		foreach (['page', 'per_page', 'chapter_id'] as $key) {
			if (!array_key_exists($key, $query)) { continue; }
			$value = is_scalar($query[$key]) ? filter_var($query[$key], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $key === 'per_page' ? 100 : intdiv(PHP_INT_MAX, 100)]]) : false;
			if ($value === false) { $invalid = true; } else { $filters[$key] = $value; }
		}
		if (array_key_exists('status', $query)) {
			if (!in_array($query['status'], self::STATUSES, true)) { $invalid = true; } else { $filters['status'] = $query['status']; }
		}
		// The frozen list route declares only 200/401/403: malformed filters match no rows.
		if ($invalid) { return ['data' => [], 'meta' => ['page' => $filters['page'], 'per_page' => $filters['per_page'], 'total' => 0, 'total_pages' => 0]]; }
		return $this->transaction->run(fn () => $this->reports->listing($filters));
	}

	public function change(int $id, mixed $body): array
	{
		Access::capability('mol_moderate_reports');
		$data = ElementInput::validate('ReportPatch', $body);
		return $this->transaction->run(function () use ($id, $data): array {
			$current = $this->reports->find($id, true);
			if (!$current) { throw Fault::missing(); }
			Access::capability('mol_moderate_reports');
			if ($current['status'] === $data['status']) { return $current; }
			$closed = in_array($data['status'], ['resolved', 'rejected'], true);
			return $this->reports->save($id, ['status' => $data['status'], 'resolved_by' => $closed ? get_current_user_id() : null, 'resolved_at' => $closed ? current_time('mysql', true) : null]);
		});
	}
}
