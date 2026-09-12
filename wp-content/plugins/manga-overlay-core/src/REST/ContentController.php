<?php
declare(strict_types=1);

namespace MOL\REST;

use MOL\Database\ChapterRepository;
use MOL\Database\OverlayReadRepository;
use MOL\Database\PageRepository;
use MOL\Domain\Fault;
use MOL\Media\MediaService;
use MOL\Security\Access;
use MOL\Security\ChapterVisibilityPolicy;
use MOL\Services\ChapterService;
use MOL\Services\PageService;

final class ContentController
{
	public function __construct(
		private readonly ChapterRepository $chapters,
		private readonly PageRepository $pages,
		private readonly OverlayReadRepository $overlays,
		private readonly ChapterService $chapter_service,
		private readonly PageService $page_service,
	) {
	}

	public function register(): void
	{
		$this->route('/capabilities', 'GET', fn ($request) => self::response(MediaService::capabilities()));
		$this->route('/works/(?P<id>[1-9][0-9]*)/chapters', 'GET', [$this, 'work_chapters']);
		$this->route('/chapters', 'POST', fn ($request) => self::response($this->chapter_service->create(self::body($request)), [], 201), 'mol_manage_content');
		$this->route('/chapters/(?P<id>[1-9][0-9]*)', 'GET', fn ($request) => self::response($this->visible($request)));
		$this->route('/chapters/(?P<id>[1-9][0-9]*)', 'PATCH', fn ($request) => self::response($this->chapter_service->update((int) $request['id'], self::body($request))), 'mol_manage_content');
		$this->route('/chapters/(?P<id>[1-9][0-9]*)', 'DELETE', function ($request) {
			$this->page_service->delete_chapter((int) $request['id']);
			return new \WP_REST_Response(null, 204);
		}, 'mol_manage_content');
		$this->route('/chapters/(?P<id>[1-9][0-9]*)/review', 'PATCH', fn ($request) => self::response($this->chapter_service->update((int) $request['id'], self::body($request), true)), 'mol_review_translations');
		$this->route('/chapters/(?P<id>[1-9][0-9]*)/pages', 'GET', [$this, 'chapter_pages']);
		$this->route('/chapters/(?P<id>[1-9][0-9]*)/pages', 'POST', [$this, 'upload'], 'mol_upload_content');
		$this->route('/chapters/(?P<id>[1-9][0-9]*)/pages/reorder', 'PATCH', function ($request) {
			$pages = $this->page_service->reorder((int) $request['id'], self::body($request));
			return self::response($pages, ['chapter_id' => (int) $request['id'], 'count' => count($pages)]);
		}, 'mol_manage_content');
		$this->route('/pages/(?P<id>[1-9][0-9]*)', 'DELETE', function ($request) {
			$this->page_service->delete_page((int) $request['id']);
			return new \WP_REST_Response(null, 204);
		}, 'mol_manage_content');
		$this->route('/pages/(?P<id>[1-9][0-9]*)/elements', 'GET', [$this, 'page_elements']);
		$this->route('/chapters/(?P<id>[1-9][0-9]*)/elements', 'GET', [$this, 'chapter_elements']);
		$this->route('/chapters/(?P<id>[1-9][0-9]*)/contributors', 'GET', function ($request) {
			$id = $this->visible($request)['id'];
			$items = $this->overlays->contributors($id);
			return self::response($items, ['chapter_id' => $id, 'count' => count($items)]);
		});
	}

	private function route(string $route, string $method, callable $callback, ?string $capability = null): void
	{
		register_rest_route('mol/v1', $route, [
			'methods' => $method,
			'permission_callback' => $capability ? static fn ($request) => self::safely(static fn () => Access::require($request, $capability)) : '__return_true',
			'callback' => static function ($request) use ($callback) {
				$result = self::safely(static fn () => $callback($request));
				if ($result instanceof \WP_REST_Response) {
					// No shared caching until publication-aware invalidation is implemented.
					$result->header('Cache-Control', 'private, no-store');
					$result->header('X-Content-Type-Options', 'nosniff');
				}
				return $result;
			},
		]);
	}

	public static function safely(callable $operation): mixed
	{
		try {
			return $operation();
		} catch (Fault $error) {
			// Clients can use the standard retry header without inspecting an implementation detail.
			if ($error->status === 429) {
				$response = new \WP_REST_Response(['code' => $error->error_code, 'message' => $error->getMessage(), 'data' => ['status' => $error->status]], 429);
				$response->header('Retry-After', (string) ($error->details['retry_after'] ?? 60));
				return $response;
			}
			return new \WP_Error($error->error_code, $error->getMessage(), ['status' => $error->status]);
		} catch (\Throwable $error) {
			return new \WP_Error('internal_server_error', 'تعذر إكمال العملية. أعد المحاولة.', ['status' => 500]);
		}
	}

	public static function body(\WP_REST_Request $request): mixed
	{
		if (!$request->is_json_content_type()) {
			throw Fault::invalid('أرسل بيانات JSON.');
		}
		try {
			return json_decode($request->get_body(), false, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $error) {
			throw Fault::invalid('بيانات JSON غير صالحة.');
		}
	}

	public static function response(mixed $data, array $meta = [], int $status = 200): \WP_REST_Response
	{
		return new \WP_REST_Response(['data' => $data, 'meta' => (object) $meta], $status);
	}

	private function visible(\WP_REST_Request $request): array
	{
		return ChapterVisibilityPolicy::require($this->chapters->find((int) $request['id']), $request);
	}

	public function work_chapters(\WP_REST_Request $request): \WP_REST_Response
	{
		$id = (int) $request['id'];
		if (get_post_type($id) !== 'mol_work' || get_post_status($id) !== 'publish') {
			throw Fault::missing();
		}
		$chapters = $this->chapters->for_work($id);
		return self::response($chapters, ['page' => 1, 'per_page' => max(1, count($chapters)), 'total' => count($chapters), 'total_pages' => $chapters ? 1 : 0]);
	}

	public function chapter_pages(\WP_REST_Request $request): \WP_REST_Response
	{
		$id = $this->visible($request)['id'];
		$items = array_map([PageRepository::class, 'to_dto'], $this->pages->for_chapter($id));
		return self::response($items, ['chapter_id' => $id, 'count' => count($items)]);
	}

	public function page_elements(\WP_REST_Request $request): \WP_REST_Response
	{
		$page = $this->pages->find((int) $request['id']);
		if (!$page) {
			throw Fault::missing();
		}
		ChapterVisibilityPolicy::require($this->chapters->find((int) $page['chapter_id']), $request);
		$lang = self::language($request);
		$items = $this->overlays->for_page((int) $page['id'], $lang);
		return self::response($items, ['page_id' => (int) $page['id'], 'target_lang' => $lang, 'count' => count($items)]);
	}

	public function chapter_elements(\WP_REST_Request $request): \WP_REST_Response
	{
		$id = $this->visible($request)['id'];
		$lang = self::language($request);
		$grouped = $this->overlays->for_chapter($id, $lang);
		$items = [];
		$count = 0;
		foreach ($this->pages->for_chapter($id) as $page) {
			$elements = $grouped[(int) $page['id']] ?? [];
			$count += count($elements);
			$items[] = ['page_id' => (int) $page['id'], 'page_index' => (int) $page['page_index'], 'elements' => $elements];
		}
		return self::response($items, ['chapter_id' => $id, 'target_lang' => $lang, 'page_count' => count($items), 'element_count' => $count]);
	}

	public function upload(\WP_REST_Request $request): \WP_REST_Response
	{
		$files = $request->get_file_params();
		if (!$files && (int) $request->get_header('Content-Length') > min(wp_max_upload_size(), (int) get_option('mol_upload_max_bytes', 20 * MB_IN_BYTES))) {
			throw new Fault('mol_payload_too_large', 'الصورة أكبر من حد الرفع.', 413);
		}
		if (array_keys($files) !== ['image'] || $request->get_body_params()) {
			throw Fault::invalid('ارفع صورة واحدة في حقل image.');
		}
		$file = $files['image'];
		if (!is_array($file) || (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !is_uploaded_file($file['tmp_name'] ?? ''))) {
			throw Fault::invalid('ملف الرفع غير صالح.');
		}
		return new \WP_REST_Response($this->page_service->upload((int) $request['id'], $file, $request->get_header('MOL-Idempotency-Key')), 201);
	}

	public static function response_headers(mixed $response, \WP_REST_Server $server, \WP_REST_Request $request): mixed
	{
		if ($response instanceof \WP_REST_Response && str_starts_with($request->get_route(), '/mol/v1/')) {
			$data = $response->get_data();
			$code = is_array($data) ? ($data['code'] ?? '') : '';
			if (in_array($code, ['rest_invalid_json', 'rest_invalid_param', 'rest_missing_callback_param'], true)) {
				$response->set_data(['code' => 'mol_invalid_params', 'message' => 'بيانات الطلب غير صالحة.', 'data' => ['status' => 400]]);
			} elseif ($code === 'rest_cookie_invalid_nonce') {
				$response->set_data(['code' => 'mol_forbidden', 'message' => 'انتهت صلاحية الجلسة. حدّث الصفحة ثم أعد المحاولة.', 'data' => ['status' => 403]]);
			}
			$response->header('Cache-Control', 'private, no-store');
			$response->header('X-Content-Type-Options', 'nosniff');
		}
		return $response;
	}

	private static function language(\WP_REST_Request $request): string
	{
		$lang = $request->get_param('lang') ?? 'ar';
		if (!is_string($lang) || mb_strlen($lang) > 255) {
			throw Fault::invalid('وسم اللغة غير صالح.');
		}
		return $lang;
	}
}
