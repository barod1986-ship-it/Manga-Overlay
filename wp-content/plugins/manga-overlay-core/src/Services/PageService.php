<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\ChapterRepository;
use MOL\Database\PageRepository;
use MOL\Database\Transaction;
use MOL\Domain\ContentInput;
use MOL\Domain\Fault;
use MOL\Media\MediaService;
use MOL\Security\Access;
use MOL\Security\RateLimiter;

final class PageService
{
	public function __construct(
		private readonly ChapterRepository $chapters,
		private readonly PageRepository $pages,
		private readonly Transaction $transaction,
		private readonly MediaService $media,
		private readonly IdempotencyService $idempotency,
		private readonly RateLimiter $limiter,
	) {
	}

	public function upload(int $chapter_id, array $file, string $key): array
	{
		Access::capability('mol_upload_content');
		$this->limiter->upload();
		if (!$this->chapters->find($chapter_id)) {
			throw Fault::missing();
		}
		$info = $this->media->inspect($file);
		$hash = hash('sha256', wp_json_encode([$chapter_id, sanitize_file_name($file['name']), $info['hash']], JSON_THROW_ON_ERROR));
		$created = null;
		try {
			$response = $this->idempotency->run('upload:' . $chapter_id, $key, $hash, function () use ($chapter_id, $file, $info, &$created): array {
				$chapter = $this->chapters->lock($chapter_id);
				if (!$chapter) {
					throw Fault::missing();
				}
				$created = $this->media->create($file, $info, $chapter['work_id']);
				$page = $this->pages->create($chapter_id, $created['attachment_id'], $info['width'], $info['height']);
				return ['data' => PageRepository::to_dto($page), 'meta' => new \stdClass()];
			});
		} catch (\Throwable $error) {
			if ($created !== null) {
				$this->media->cleanup($created);
			}
			throw $error;
		}
		if ($created !== null) {
			do_action('mol_after_page_uploaded', $response['data']['id'], $chapter_id, get_current_user_id());
		}
		return $response;
	}

	public function reorder(int $id, mixed $body): array
	{
		Access::capability('mol_manage_content');
		try {
			$data = ContentInput::validate('PageReorder', $body);
		} catch (Fault $error) {
			throw new Fault('mol_invalid_reorder', 'أرسل كل صفحات الفصل دون تكرار.', 400);
		}
		return $this->transaction->run(function () use ($id, $data): array {
			if (!$this->chapters->lock($id)) {
				throw Fault::missing();
			}
			$this->pages->reorder($id, $data['page_ids']);
			return array_map([PageRepository::class, 'to_dto'], $this->pages->for_chapter($id));
		});
	}

	public function delete_page(int $id): void
	{
		Access::capability('mol_manage_content');
		$this->transaction->run(function () use ($id): void {
			$page = $this->pages->find($id);
			if (!$page || !$this->chapters->lock((int) $page['chapter_id']) || !$this->pages->find($id, true)) {
				throw Fault::missing();
			}
			$this->pages->delete($id);
			// Read current rows after the chapter lock, not the earlier transaction snapshot.
			$remaining = $this->pages->for_chapter((int) $page['chapter_id'], true);
			if ($remaining) {
				$this->pages->reorder((int) $page['chapter_id'], array_map('intval', array_column($remaining, 'id')));
			}
			$this->pages->clamp_progress((int) $page['chapter_id'], count($remaining) - 1);
		});
	}

	public function delete_chapter(int $id): void
	{
		Access::capability('mol_manage_content');
		$this->transaction->run(function () use ($id): void {
			if (!$this->chapters->lock($id)) {
				throw Fault::missing();
			}
			$this->pages->delete_chapter($id);
		});
	}
}
