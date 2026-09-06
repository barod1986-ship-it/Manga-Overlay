<?php
declare(strict_types=1);

namespace MOL\Services;

use MOL\Database\ChapterRepository;
use MOL\Database\IdempotencyRepository;
use MOL\Database\OverlayReadRepository;
use MOL\Database\PageRepository;
use MOL\Database\RateLimitRepository;
use MOL\Database\Transaction;
use MOL\Media\MediaService;
use MOL\REST\ContentController;
use MOL\Security\RateLimiter;

final class ContentRuntime
{
	public readonly ChapterRepository $chapters;
	public readonly PageRepository $pages;
	public readonly ChapterService $chapter_service;
	public readonly PageService $page_service;
	public readonly ContentController $controller;

	public function __construct(\wpdb $db)
	{
		$this->chapters = new ChapterRepository($db);
		$this->pages = new PageRepository($db);
		$transaction = new Transaction($db);
		$this->chapter_service = new ChapterService($this->chapters, $transaction);
		$this->page_service = new PageService($this->chapters, $this->pages, $transaction, new MediaService(), new IdempotencyService(new IdempotencyRepository($db), $transaction), new RateLimiter(new RateLimitRepository($db)));
		$this->controller = new ContentController($this->chapters, $this->pages, new OverlayReadRepository($db), $this->chapter_service, $this->page_service);
	}

	public static function cleanup(): void
	{
		global $wpdb;
		(new IdempotencyRepository($wpdb))->cleanup();
		(new RateLimitRepository($wpdb))->cleanup();
	}
}
