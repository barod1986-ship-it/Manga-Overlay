<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Database\TransactionManager;
use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\IdempotencyKeyRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReadingProgressRepository;
use MOL\Repositories\WorkRepository;
use MOL\Services\ChapterService;
use MOL\Services\ContentDeletionService;
use MOL\Services\MediaService;
use MOL\Services\PageReorderService;
use MOL\Services\PageUploadService;
use MOL\Services\PublicReadService;
use MOL\Services\ReadingProgressService;

final class Routes
{
    private const API_NAMESPACE = 'mol/v1';

    public function registerHooks(): void
    {
        add_action('rest_api_init', array($this, 'register'));
    }

    public function register(): void
    {
        global $wpdb;
        if (! $wpdb instanceof \wpdb) {
            return;
        }

        $chapters = new ChapterRepository($wpdb);
        $pages = new PageRepository($wpdb);
        $elements = new ElementRepository($wpdb);
        $contributions = new ContributionRepository($wpdb);
        $works = new WorkRepository($wpdb);
        $transactions = new TransactionManager($wpdb);
        $deletions = new ContentDeletionService($wpdb, $transactions);
        $chapterController = new ChapterController(new ChapterService($chapters), $deletions);
        $pageController = new PageController(
            new PageUploadService(
                $chapters,
                $pages,
                new IdempotencyKeyRepository($wpdb),
                $transactions
            ),
            new PageReorderService($chapters, $pages, $transactions),
            $deletions
        );
        $reads = new PublicReadService(
            $works,
            $chapters,
            $pages,
            $elements,
            $contributions,
            new ChapterVisibilityPolicy()
        );
        $libraryController = new LibraryController($works, $chapters);
        $publicReadController = new PublicReadController($reads);
        $readingProgressController = new ReadingProgressController(
            new ReadingProgressService(new ReadingProgressRepository($wpdb), $reads)
        );
        $profileController = new ProfileController($contributions);

        register_rest_route(self::API_NAMESPACE, '/library', array(
            array(
                'methods' => 'GET',
                'callback' => array($libraryController, 'listWorks'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/capabilities', array(
            array(
                'methods' => 'GET',
                'callback' => array(new CapabilitiesController(new MediaService()), 'get'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/works/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($libraryController, 'getWork'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/works/(?P<id>\d+)/chapters', array(
            array(
                'methods' => 'GET',
                'callback' => array($libraryController, 'listWorkChapters'),
                'permission_callback' => '__return_true',
            ),
        ));

        register_rest_route(self::API_NAMESPACE, '/chapters', array(
            array(
                'methods' => 'POST',
                'callback' => array($chapterController, 'create'),
                'permission_callback' => array(Permissions::class, 'manageContent'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($publicReadController, 'getChapter'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => 'PATCH',
                'callback' => array($chapterController, 'update'),
                'permission_callback' => array(Permissions::class, 'manageContent'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($chapterController, 'delete'),
                'permission_callback' => array(Permissions::class, 'manageContent'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)/review', array(
            array(
                'methods' => 'PATCH',
                'callback' => array($chapterController, 'review'),
                'permission_callback' => array(Permissions::class, 'reviewTranslations'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)/pages', array(
            array(
                'methods' => 'GET',
                'callback' => array($publicReadController, 'listChapterPages'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods' => 'POST',
                'callback' => array($pageController, 'upload'),
                'permission_callback' => array(Permissions::class, 'uploadContent'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)/pages/reorder', array(
            array(
                'methods' => 'PATCH',
                'callback' => array($pageController, 'reorder'),
                'permission_callback' => array(Permissions::class, 'manageContent'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/pages/(?P<id>\d+)', array(
            array(
                'methods' => 'DELETE',
                'callback' => array($pageController, 'delete'),
                'permission_callback' => array(Permissions::class, 'manageContent'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/pages/(?P<id>\d+)/elements', array(
            array(
                'methods' => 'GET',
                'callback' => array($publicReadController, 'listPageElements'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)/elements', array(
            array(
                'methods' => 'GET',
                'callback' => array($publicReadController, 'listChapterElements'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)/contributors', array(
            array(
                'methods' => 'GET',
                'callback' => array($publicReadController, 'listChapterContributors'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/profiles/(?P<username>[^/]+)', array(
            array(
                'methods' => 'GET',
                'callback' => array($profileController, 'get'),
                'permission_callback' => '__return_true',
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/reading-progress', array(
            array(
                'methods' => 'PUT',
                'callback' => array($readingProgressController, 'save'),
                'permission_callback' => array(Permissions::class, 'authenticatedUser'),
            ),
        ));
    }
}
