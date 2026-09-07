<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\{ChapterRepository, ElementRepository, IdempotencyRepository, LockRepository, PageRepository, RateLimitRepository, Transaction};
use MOL\Domain\{ElementInput, Fault};
use MOL\Security\{Access, RateLimiter};

final class ElementService
{
	private readonly ElementRepository $elements;
	private readonly ChapterRepository $chapters;
	private readonly PageRepository $pages;
	private readonly LockRepository $locks;
	private readonly Transaction $transaction;
	private readonly IdempotencyService $idempotency;
	private readonly ElementStyles $styles;
	private readonly RateLimiter $limiter;

	public function __construct(\wpdb $db)
	{
		$this->elements = new ElementRepository($db);
		$this->chapters = new ChapterRepository($db);
		$this->pages = new PageRepository($db);
		$this->locks = new LockRepository($db);
		$this->transaction = new Transaction($db);
		$this->idempotency = new IdempotencyService(new IdempotencyRepository($db), $this->transaction);
		$this->styles = new ElementStyles($db);
		$this->limiter = new RateLimiter(new RateLimitRepository($db));
	}

	public function create(mixed $body, string $key): array
	{
		Access::capability('mol_use_editor');
		Access::capability('mol_edit_translations');
		$this->limiter->element();
		$data = ElementInput::validate('ElementCreate', $body);
		$hash = hash('sha256', wp_json_encode(self::canonical($body), JSON_THROW_ON_ERROR));
		$created = null;
		$response = $this->idempotency->run('element:create', $key, $hash, function () use ($data, &$created): array {
			try {
				[$page, $chapter] = $this->context((int) $data['page_id']);
			} catch (Fault $error) {
				// Create's frozen contract reports invalid parents as invalid parameters.
				throw Fault::invalid('صفحة العنصر غير متاحة.');
			}
			$style = $this->styles->resolve($data['element_type'], $chapter['work_id'], isset($data['preset_id']) ? (int) $data['preset_id'] : null, $data['style'] ?? new \stdClass());
			$now = current_time('mysql', true);
			$element = $this->elements->insert(ElementInput::geometry($data) + [
				'page_id' => $page['id'], 'target_lang' => $data['target_lang'], 'element_type' => $data['element_type'],
				'content' => sanitize_textarea_field($data['content']), 'style_json' => wp_json_encode($style, JSON_THROW_ON_ERROR),
				'version' => 1, 'created_by' => get_current_user_id(), 'updated_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
			]);
			$this->elements->contribute($element, $chapter, true);
			$created = [$element['id'], $page['id'], $chapter['id'], get_current_user_id()];
			return ['data' => $element, 'meta' => new \stdClass()];
		});
		if ($created !== null) {
			do_action('mol_after_element_saved', ...$created);
		}
		return $response;
	}

	private static function canonical(mixed $value): mixed
	{
		if ($value instanceof \stdClass) {
			$properties = get_object_vars($value);
			ksort($properties);
			return (object) array_map([self::class, 'canonical'], $properties);
		}
		return $value;
	}

	public function change(int $id, mixed $body, string $match, string $token, bool $delete = false): ?array
	{
		Access::capability('mol_use_editor');
		Access::capability($delete ? 'mol_delete_translation_elements' : 'mol_edit_translations');
		$this->limiter->element();
		$data = $delete ? [] : ElementInput::validate('ElementPatch', $body);
		$hook = [];
		$result = $this->transaction->run(function () use ($id, $data, $match, $token, $delete, &$hook): ?array {
			[$element, $chapter] = $this->locked_element($id);
			$lease = $this->locks->find($id);
			if (!$this->owns($lease, $token)) {
				throw $this->locked($lease);
			}
			if ($match === '') {
				throw new Fault('mol_precondition_required', 'رقم نسخة العنصر مطلوب للحفظ.', 428);
			}
			if ($match !== '"' . $element['version'] . '"') {
				throw new Fault('mol_version_conflict', 'تغير العنصر منذ تحميله. قارن النسختين قبل الحفظ.', 412);
			}
			$hook = [$id, $element['page_id'], $chapter['id'], get_current_user_id()];
			if ($delete) {
				$this->elements->delete($id);
				return null;
			}
			if (isset($data['element_type']) && $data['element_type'] !== $element['element_type']) {
				throw Fault::invalid('لا يمكن تغيير نوع عنصر محفوظ.');
			}
			$changes = ElementInput::geometry(array_replace($element, $data));
			if (isset($data['content'])) {
				$changes['content'] = sanitize_textarea_field($data['content']);
			}
			if (isset($data['style'])) {
				$changes['style_json'] = wp_json_encode(ElementInput::style($element['element_type'], ElementStyles::merge($element['style'], $data['style'])), JSON_THROW_ON_ERROR);
			}
			$changes += ['version' => $element['version'] + 1, 'updated_by' => get_current_user_id(), 'updated_at' => current_time('mysql', true)];
			$element = $this->elements->update($id, $changes);
			$this->elements->contribute($element, $chapter, false);
			return $element;
		});
		do_action($delete ? 'mol_after_element_deleted' : 'mol_after_element_saved', ...$hook);
		return $result;
	}

	public function lease(int $id, string $method, string $token): ?array
	{
		if ($method !== 'DELETE') {
			Access::capability('mol_use_editor');
			Access::capability('mol_edit_translations');
		}
		if ($method === 'POST') {
			$this->limiter->lock();
		}
		return $this->transaction->run(function () use ($id, $method, $token): ?array {
			$this->locked_element($id);
			$lease = $this->locks->find($id);
			if ($method === 'DELETE' && current_user_can('mol_manage_content')) {
				$this->locks->delete($id);
				return null;
			}
			if ($method === 'POST') {
				if ($lease && $lease['expires_at'] > current_time('mysql', true) && (int) $lease['user_id'] !== get_current_user_id()) {
					throw $this->locked($lease);
				}
				$active = $lease && $lease['expires_at'] > current_time('mysql', true);
				return $this->locks->save($id, get_current_user_id(), $active ? $lease['lock_token'] : bin2hex(random_bytes(32)), (bool) $active);
			}
			if ($method === 'DELETE' && $lease && $lease['expires_at'] > current_time('mysql', true) && (int) $lease['user_id'] !== get_current_user_id()) {
				throw new Fault('mol_forbidden', 'لا تملك صلاحية تحرير قفل مستخدم آخر.', 403);
			}
			if (!$this->owns($lease, $token)) {
				throw new Fault('mol_lock_lost', 'انتهى قفل العنصر أو تغير. استعد القفل قبل المتابعة.', 409);
			}
			if ($method === 'DELETE') {
				$this->locks->delete($id);
				return null;
			}
			return $this->locks->save($id, get_current_user_id(), $token, true);
		});
	}

	/** Same lock order as page/chapter deletion; recheck descendants after acquiring parent. */
	private function context(int $page_id): array
	{
		$page = $this->pages->find($page_id);
		$chapter = $page ? $this->chapters->lock((int) $page['chapter_id']) : null;
		// A normal SELECT here can reuse the pre-lock REPEATABLE READ snapshot.
		if (!$chapter || !($page = $this->pages->find($page_id, true))) {
			throw Fault::missing();
		}
		return [$page, $chapter];
	}

	private function locked_element(int $id): array
	{
		$element = $this->elements->find($id);
		if (!$element) {
			throw Fault::missing();
		}
		[, $chapter] = $this->context($element['page_id']);
		$element = $this->elements->find($id, true);
		if (!$element) {
			throw Fault::missing();
		}
		return [$element, $chapter];
	}

	private function owns(?array $lease, string $token): bool
	{
		return $lease && $lease['expires_at'] > current_time('mysql', true) && (int) $lease['user_id'] === get_current_user_id() && hash_equals($lease['lock_token'], $token);
	}

	private function locked(?array $lease): Fault
	{
		$user = $lease && $lease['expires_at'] > current_time('mysql', true) ? get_userdata((int) $lease['user_id']) : false;
		return new Fault('mol_element_locked', $user ? 'العنصر قيد التحرير بواسطة ' . $user->display_name . '.' : 'يلزم قفل نشط باسمك قبل تعديل العنصر.', 423);
	}
}
