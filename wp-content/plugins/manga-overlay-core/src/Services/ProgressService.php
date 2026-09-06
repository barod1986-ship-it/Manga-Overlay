<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\ChapterRepository;
use MOL\Database\PageRepository;
use MOL\Database\ProgressRepository;
use MOL\Database\Transaction;
use MOL\Domain\ContentInput;
use MOL\Domain\Fault;
use MOL\Security\ChapterVisibilityPolicy;

final class ProgressService
{
	public function __construct(private readonly \wpdb $db)
	{
	}

	public function save(mixed $body): array
	{
		if (!is_user_logged_in()) {
			throw new Fault('mol_not_authenticated', 'سجّل الدخول للمتابعة.', 401);
		}
		$data = ContentInput::validate('ReadingProgressUpdate', $body);
		return (new Transaction($this->db))->run(function () use ($data): array {
			// The same lock serializes progress with page reorder/delete.
			$chapter = (new ChapterRepository($this->db))->lock($data['chapter_id']);
			try {
				ChapterVisibilityPolicy::require($chapter);
			} catch (Fault $error) {
				// This route specifies 400, not a resource-specific 404.
				throw Fault::invalid('موضع القراءة غير متاح.');
			}
			$pages = (new PageRepository($this->db))->for_chapter($data['chapter_id']);
			if ($data['page_index'] >= count($pages)) {
				throw Fault::invalid('موضع القراءة غير متاح.');
			}
			return (new ProgressRepository($this->db))->save(get_current_user_id(), $data);
		});
	}
}
