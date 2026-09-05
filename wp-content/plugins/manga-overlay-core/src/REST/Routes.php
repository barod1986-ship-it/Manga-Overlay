<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Database\TransactionManager;
use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ContributionRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\ElementLockRepository;
use MOL\Repositories\IdempotencyKeyRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\ReadingProgressRepository;
use MOL\Repositories\ReportRepository;
use MOL\Repositories\StylePresetRepository;
use MOL\Repositories\WorkRepository;
use MOL\Services\ChapterService;
use MOL\Services\ContentDeletionService;
use MOL\Services\MediaService;
use MOL\Services\ElementLockService;
use MOL\Services\ElementStyleResolver;
use MOL\Services\ElementWriteService;
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
        $elementLocks = new ElementLockRepository($wpdb);
        $contributions = new ContributionRepository($wpdb);
        $works = new WorkRepository($wpdb);
        $transactions = new TransactionManager($wpdb);
        $elementController = new ElementController(new ElementWriteService(
            $chapters,
            $pages,
            $elements,
            $elementLocks,
            $contributions,
            new ReportRepository($wpdb),
            new IdempotencyKeyRepository($wpdb),
            new ElementStyleResolver(new StylePresetRepository($wpdb)),
            $transactions
        ));
        $elementLockController = new ElementLockController(new ElementLockService(
            $chapters,
            $pages,
            $elements,
            $elementLocks,
            $transactions
        ));
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
        register_rest_route(self::API_NAMESPACE, '/elements', array(
            array(
                'methods' => 'POST',
                'callback' => array($elementController, 'create'),
                'permission_callback' => array(Permissions::class, 'editTranslations'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/elements/(?P<id>\d+)', array(
            array(
                'methods' => 'PATCH',
                'callback' => array($elementController, 'update'),
                'permission_callback' => array(Permissions::class, 'editTranslations'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($elementController, 'delete'),
                'permission_callback' => array(Permissions::class, 'deleteTranslationElements'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/elements/(?P<id>\d+)/lock', array(
            array(
                'methods' => 'POST',
                'callback' => array($elementLockController, 'acquire'),
                'permission_callback' => array(Permissions::class, 'editTranslations'),
            ),
            array(
                'methods' => 'PUT',
                'callback' => array($elementLockController, 'renew'),
                'permission_callback' => array(Permissions::class, 'editTranslations'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($elementLockController, 'release'),
                'permission_callback' => array(Permissions::class, 'releaseElementLock'),
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
