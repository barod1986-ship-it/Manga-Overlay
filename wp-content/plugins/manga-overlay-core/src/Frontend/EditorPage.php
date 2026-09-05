<?php

declare(strict_types=1);

namespace MOL\Frontend;

use MOL\Content\WorkContent;
use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\ElementRepository;
use MOL\Repositories\PageRepository;
use MOL\Repositories\WorkRepository;
use MOL\REST\ChapterPresenter;
use MOL\REST\ElementPresenter;
use MOL\REST\PagePresenter;
use MOL\Services\EditorContextService;

final class EditorPage
{
    public const TARGET_LANGUAGE = 'ar';

    public function registerHooks(): void
    {
        add_filter('template_include', array($this, 'template'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'), 100);
        add_filter('body_class', array($this, 'bodyClasses'));
    }

    public function template(string $template): string
    {
        if (! self::isEditorRequest()) {
            return $template;
        }

        return MOL_PLUGIN_DIR . 'templates/editor.php';
    }

    public function enqueueAssets(): void
    {
        if (! self::isEditorRequest() || ! self::currentUserCanEdit()) {
            return;
        }

        // The editor owns this document even when the active public theme also
        // recognizes the chapter query variable as a reader request.
        wp_dequeue_style('manga-overlay-theme');
        wp_dequeue_style('manga-overlay-reader');
        wp_dequeue_script('manga-overlay-theme');
        wp_dequeue_script('manga-overlay-reader');

        wp_enqueue_style(
            'mol-editor',
            plugins_url('assets/dist/editor.css', MOL_PLUGIN_FILE),
            array(),
            MOL_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'mol-editor',
            plugins_url('assets/dist/editor.js', MOL_PLUGIN_FILE),
            array('wp-element'),
            MOL_PLUGIN_VERSION,
            array('in_footer' => true, 'strategy' => 'defer')
        );
    }

    /** @param list<string> $classes @return list<string> */
    public function bodyClasses(array $classes): array
    {
        if (self::isEditorRequest()) {
            $classes = array_values(array_diff($classes, array('mol-reader-page')));
            $classes[] = 'mol-editor-page';
        }

        return array_values(array_unique($classes));
    }

    public static function isEditorRequest(): bool
    {
        return is_singular(WorkContent::POST_TYPE)
            && '1' === (string) get_query_var('mol_editor')
            && '' !== trim((string) get_query_var('mol_chapter'));
    }

    public static function currentUserCanEdit(): bool
    {
        return get_current_user_id() > 0
            && (current_user_can('mol_use_editor') || current_user_can('mol_manage_content'));
    }

    /**
     * @return array{
     *   work: array{id: int, slug: string, title: string, status: string},
     *   chapter: array<string, mixed>,
     *   pages: list<array<string, mixed>>,
     *   targetLanguage: string,
     *   api: array{root: string, nonce: string},
     *   links: array{work: string, reader: string|null},
     *   permissions: array{manageWorkPresets: bool, manageGlobalPresets: bool},
     *   release: array{core: string}
     * }
     */
    public static function bootstrap(): array
    {
        global $wpdb;
        if (! $wpdb instanceof \wpdb) {
            throw new \RuntimeException('WordPress database is unavailable.');
        }

        $workId = get_queried_object_id();
        $chapterSlug = (string) get_query_var('mol_chapter');
        $service = new EditorContextService(
            new WorkRepository($wpdb),
            new ChapterRepository($wpdb),
            new PageRepository($wpdb),
            new ElementRepository($wpdb),
            new ChapterVisibilityPolicy()
        );
        $context = $service->load(
            $workId,
            $chapterSlug,
            get_current_user_id(),
            self::TARGET_LANGUAGE
        );
        $work = $context['work'];
        $chapter = ChapterPresenter::one($context['chapter']);
        $pageGroups = array_map(
            static function (array $group): array {
                $page = PagePresenter::one($group['page']);
                $page['elements'] = ElementPresenter::many($group['elements']);

                return $page;
            },
            $context['pages']
        );

        $workUrl = get_permalink($workId);
        $workUrl = is_string($workUrl) ? $workUrl : home_url('/');
        $readerUrl = null;
        if ('publish' === $work->post_status && true === $chapter['is_published']) {
            $readerUrl = home_url(sprintf(
                '/series/%s/chapter/%s/',
                rawurlencode((string) $work->post_name),
                rawurlencode((string) $chapter['slug'])
            ));
        }

        return array(
            'work' => array(
                'id' => (int) $work->ID,
                'slug' => (string) $work->post_name,
                'title' => get_the_title($work),
                'status' => (string) $work->post_status,
            ),
            'chapter' => $chapter,
            'pages' => $pageGroups,
            'targetLanguage' => self::TARGET_LANGUAGE,
            'api' => array(
                'root' => esc_url_raw(rest_url('mol/v1/')),
                'nonce' => wp_create_nonce('wp_rest'),
            ),
            'links' => array(
                'work' => $workUrl,
                'reader' => $readerUrl,
            ),
            'permissions' => array(
                'manageWorkPresets' => current_user_can('mol_manage_work_presets'),
                'manageGlobalPresets' => current_user_can('mol_manage_global_presets'),
            ),
            'release' => array('core' => MOL_PLUGIN_VERSION),
        );
    }
}
