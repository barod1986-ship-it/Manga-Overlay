<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\ChapterRepository;
use MOL\Database\Transaction;
use MOL\Database\WorkMutationLock;
use MOL\Domain\ContentInput;
use MOL\Domain\Fault;
use MOL\Security\Access;

final class ChapterService
{
	public function __construct(private readonly ChapterRepository $chapters, private readonly Transaction $transaction)
	{
	}

	public function create(mixed $body): array
	{
		Access::capability('mol_manage_content');
		$data = ContentInput::validate('ChapterCreate', $body);
		return WorkMutationLock::run($data['work_id'], function () use ($data): array {
			if (get_post_type($data['work_id']) !== 'mol_work' || get_post_status($data['work_id']) === 'trash') {
				throw Fault::invalid('اختر عملًا موجودًا.');
			}
			$now = current_time('mysql', true);
			$data += ['sort_order' => 0, 'translation_status' => 'untranslated', 'is_published' => false];
			$data += ['created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now, 'published_at' => $data['is_published'] ? $now : null];
			$base = sanitize_title(trim($data['title'] ?? '') !== '' ? $data['title'] : 'chapter-' . $data['chapter_label']);
			$base = $base !== '' ? $base : 'chapter';
			return $this->transaction->run(function () use ($data, $base): array {
				for ($attempt = 1; $attempt <= 20; ++$attempt) {
					$suffix = $attempt === 1 ? '' : '-' . $attempt;
					// Core truncation preserves percent-encoded Arabic character boundaries.
					$data['slug'] = _truncate_post_slug($base, 190 - strlen($suffix)) . $suffix;
					$id = $this->chapters->insert($data);
					if ($id !== null) {
						return $this->chapters->find($id);
					}
				}
				throw new Fault('mol_slug_conflict', 'تعذر حجز رابط فريد للفصل. غيّر التسمية وحاول مجددًا.', 409);
			});
		});
	}

	public function update(int $id, mixed $body, bool $review_only = false): array
	{
		Access::capability($review_only ? 'mol_review_translations' : 'mol_manage_content');
		$data = ContentInput::validate($review_only ? 'ChapterReviewPatch' : 'ChapterPatch', $body);
		$result = $this->transaction->run(function () use ($id, $data): array {
			$chapter = $this->chapters->lock($id);
			if (!$chapter) {
				throw Fault::missing();
			}
			$data['updated_at'] = current_time('mysql', true);
			if (array_key_exists('is_published', $data)) {
				$data['published_at'] = $data['is_published'] ? ($chapter['published_at'] ? gmdate('Y-m-d H:i:s', strtotime($chapter['published_at'])) : $data['updated_at']) : null;
			}
			$this->chapters->update($id, $data);
			return $this->chapters->find($id);
		});
		if (isset($data['translation_status'])) {
			do_action('mol_after_chapter_status_changed', $id, $data['translation_status'], get_current_user_id());
		}
		return $result;
	}
}
