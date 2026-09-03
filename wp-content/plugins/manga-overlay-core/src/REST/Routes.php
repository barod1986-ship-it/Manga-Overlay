<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Database\TransactionManager;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\IdempotencyKeyRepository;
use MOL\Repositories\PageRepository;
use MOL\Services\ChapterService;
use MOL\Services\ContentDeletionService;
use MOL\Services\PageReorderService;
use MOL\Services\PageUploadService;

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

        register_rest_route(self::API_NAMESPACE, '/chapters', array(
            array(
                'methods' => 'POST',
                'callback' => array($chapterController, 'create'),
                'permission_callback' => array(Permissions::class, 'manageContent'),
            ),
        ));
        register_rest_route(self::API_NAMESPACE, '/chapters/(?P<id>\d+)', array(
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
    }
}
