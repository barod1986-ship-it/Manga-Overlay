<?php

declare(strict_types=1);

namespace MOL\Admin;

use MOL\Content\WorkContent;
use MOL\Repositories\ChapterRepository;
use MOL\Repositories\PageRepository;

final class ChapterAdmin
{
    public const CHAPTERS_PAGE = 'mol-chapters';
    public const UPLOAD_PAGE = 'mol-upload-chapter';

    public function registerHooks(): void
    {
        add_action('admin_menu', array($this, 'registerMenus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function registerMenus(): void
    {
        $parent = 'edit.php?post_type=' . WorkContent::POST_TYPE;
        add_submenu_page(
            $parent,
            __('Manga Overlay Chapters', 'manga-overlay-core'),
            __('Chapters', 'manga-overlay-core'),
            'mol_manage_content',
            self::CHAPTERS_PAGE,
            array($this, 'renderChapters')
        );
        if (current_user_can('mol_manage_content')) {
            add_submenu_page(
                $parent,
                __('Upload Manga Chapter', 'manga-overlay-core'),
                __('Upload Chapter', 'manga-overlay-core'),
                'mol_upload_content',
                self::UPLOAD_PAGE,
                array($this, 'renderUpload')
            );
        } elseif (current_user_can('mol_upload_content')) {
            add_menu_page(
                __('Manga Overlay', 'manga-overlay-core'),
                __('Manga Overlay', 'manga-overlay-core'),
                'mol_upload_content',
                self::UPLOAD_PAGE,
                array($this, 'renderUpload'),
                'dashicons-images-alt2',
                26
            );
        }
    }

    public function enqueueAssets(): void
    {
        $rawPage = isset($_GET['page']) && is_string($_GET['page']) ? wp_unslash($_GET['page']) : '';
        $page = sanitize_key($rawPage);
        if (! in_array($page, array(self::CHAPTERS_PAGE, self::UPLOAD_PAGE), true)) {
            return;
        }

        $baseUrl = plugin_dir_url(MOL_PLUGIN_FILE);
        wp_enqueue_style(
            'mol-content-admin',
            $baseUrl . 'assets/admin-content.css',
            array(),
            MOL_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'mol-content-admin',
            $baseUrl . 'assets/admin-content.js',
            array(),
            MOL_PLUGIN_VERSION,
            true
        );
        wp_localize_script('mol-content-admin', 'molContentAdmin', array(
            'restRoot' => esc_url_raw(rest_url('mol/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'maxConcurrency' => 2,
            'canManage' => current_user_can('mol_manage_content'),
        ));
    }

    public function renderChapters(): void
    {
        if (! current_user_can('mol_manage_content')) {
            wp_die(esc_html__('You are not allowed to manage chapters.', 'manga-overlay-core'));
        }
        global $wpdb;
        $chapters = (new ChapterRepository($wpdb))->recent();
        $works = $this->works();
        ?>
        <div class="wrap mol-content-admin">
            <h1><?php esc_html_e('Manga Overlay Chapters', 'manga-overlay-core'); ?></h1>
            <div class="notice inline mol-admin-notice" hidden><p></p></div>
            <form class="mol-panel mol-chapter-create">
                <h2><?php esc_html_e('Add chapter', 'manga-overlay-core'); ?></h2>
                <div class="mol-form-grid">
                    <label><?php esc_html_e('Work', 'manga-overlay-core'); ?>
                        <select name="work_id" required><?php $this->workOptions($works); ?></select>
                    </label>
                    <label><?php esc_html_e('Chapter label', 'manga-overlay-core'); ?>
                        <input name="chapter_label" maxlength="64" required>
                    </label>
                    <label><?php esc_html_e('Sort order', 'manga-overlay-core'); ?>
                        <input name="sort_order" type="number" step="0.0001" value="0" required>
                    </label>
                    <label><?php esc_html_e('Optional title', 'manga-overlay-core'); ?>
                        <input name="title" maxlength="255">
                    </label>
                    <?php $this->statusSelect('translation_status', 'untranslated'); ?>
                    <?php $this->overrideSelect('reader_mode_override', __('Reader mode', 'manga-overlay-core'), array('webtoon', 'paged')); ?>
                    <?php $this->overrideSelect('direction_override', __('Direction', 'manga-overlay-core'), array('rtl', 'ltr')); ?>
                    <label class="mol-checkbox"><input name="is_published" type="checkbox">
                        <?php esc_html_e('Published', 'manga-overlay-core'); ?>
                    </label>
                </div>
                <?php submit_button(__('Create chapter', 'manga-overlay-core'), 'primary', 'submit', false); ?>
            </form>

            <div class="mol-panel">
                <h2><?php esc_html_e('Recent chapters', 'manga-overlay-core'); ?></h2>
                <div class="mol-table-scroll">
                    <table class="widefat striped mol-chapter-table">
                        <thead><tr>
                            <th><?php esc_html_e('Work', 'manga-overlay-core'); ?></th>
                            <th><?php esc_html_e('Label / title', 'manga-overlay-core'); ?></th>
                            <th><?php esc_html_e('Order', 'manga-overlay-core'); ?></th>
                            <th><?php esc_html_e('Status', 'manga-overlay-core'); ?></th>
                            <th><?php esc_html_e('Published', 'manga-overlay-core'); ?></th>
                            <th><?php esc_html_e('Actions', 'manga-overlay-core'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($chapters as $chapter) : ?>
                            <tr data-chapter-id="<?php echo esc_attr((string) $chapter['id']); ?>">
                                <td><?php echo esc_html(get_the_title((int) $chapter['work_id'])); ?></td>
                                <td>
                                    <input name="chapter_label" maxlength="64" value="<?php echo esc_attr((string) $chapter['chapter_label']); ?>">
                                    <input name="title" maxlength="255" placeholder="<?php esc_attr_e('Optional title', 'manga-overlay-core'); ?>" value="<?php echo esc_attr((string) ($chapter['title'] ?? '')); ?>">
                                    <code><?php echo esc_html((string) $chapter['slug']); ?></code>
                                </td>
                                <td><input name="sort_order" type="number" step="0.0001" value="<?php echo esc_attr((string) $chapter['sort_order']); ?>"></td>
                                <td><?php $this->statusSelect('translation_status', (string) $chapter['translation_status'], false); ?></td>
                                <td><input name="is_published" type="checkbox" <?php checked((bool) $chapter['is_published']); ?>></td>
                                <td class="mol-actions">
                                    <button type="button" class="button button-primary mol-save-chapter"><?php esc_html_e('Save', 'manga-overlay-core'); ?></button>
                                    <button type="button" class="button-link-delete mol-delete-chapter"><?php esc_html_e('Delete', 'manga-overlay-core'); ?></button>
                                    <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=' . WorkContent::POST_TYPE . '&page=' . self::UPLOAD_PAGE . '&chapter_id=' . (int) $chapter['id'])); ?>"><?php esc_html_e('Pages', 'manga-overlay-core'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderUpload(): void
    {
        if (! current_user_can('mol_upload_content')) {
            wp_die(esc_html__('You are not allowed to upload chapter pages.', 'manga-overlay-core'));
        }
        global $wpdb;
        $chapterRepository = new ChapterRepository($wpdb);
        $chapters = array_values(array_filter(
            $chapterRepository->recent(500),
            static fn (array $chapter): bool => (bool) apply_filters(
                'mol_user_can_upload_chapter',
                true,
                get_current_user_id(),
                $chapter
            )
        ));
        $requestedChapterId = isset($_GET['chapter_id']) && is_scalar($_GET['chapter_id'])
            ? absint($_GET['chapter_id'])
            : 0;
        $chapterIds = array_column($chapters, 'id');
        $chapterId = in_array($requestedChapterId, $chapterIds, true)
            ? $requestedChapterId
            : (int) ($chapterIds[0] ?? 0);
        $pages = 0 < $chapterId ? (new PageRepository($wpdb))->forChapter($chapterId) : array();
        ?>
        <div class="wrap mol-content-admin mol-upload-admin" data-chapter-id="<?php echo esc_attr((string) $chapterId); ?>">
            <h1><?php esc_html_e('Upload Chapter Pages', 'manga-overlay-core'); ?></h1>
            <div class="notice inline mol-admin-notice" hidden><p></p></div>
            <div class="mol-panel">
                <label><?php esc_html_e('Chapter', 'manga-overlay-core'); ?>
                    <select class="mol-upload-chapter-select">
                        <?php foreach ($chapters as $chapter) : ?>
                            <option value="<?php echo esc_attr((string) $chapter['id']); ?>" <?php selected($chapterId, (int) $chapter['id']); ?>>
                                <?php echo esc_html(sprintf('%s — %s', get_the_title((int) $chapter['work_id']), $chapter['chapter_label'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <?php if (0 < $chapterId) : ?>
                <div class="mol-drop-zone" tabindex="0">
                    <strong><?php esc_html_e('Drop JPEG, PNG, or WebP files here', 'manga-overlay-core'); ?></strong>
                    <span><?php esc_html_e('or choose multiple images; filenames are naturally sorted.', 'manga-overlay-core'); ?></span>
                    <input class="mol-file-input" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
                </div>
                <ol class="mol-page-queue">
                    <?php foreach ($pages as $page) :
                        $image = wp_get_attachment_image_src((int) $page['attachment_id'], 'thumbnail');
                        ?>
                        <li data-page-id="<?php echo esc_attr((string) $page['id']); ?>" data-state="uploaded">
                            <span class="mol-drag-handle" aria-hidden="true">⋮⋮</span>
                            <?php if (is_array($image)) : ?><img src="<?php echo esc_url($image[0]); ?>" alt=""><?php endif; ?>
                            <span class="mol-file-name"><?php echo esc_html(basename((string) get_attached_file((int) $page['attachment_id']))); ?></span>
                            <span class="mol-upload-state"><?php esc_html_e('Uploaded', 'manga-overlay-core'); ?></span>
                            <span class="mol-order-actions">
                                <button type="button" class="button mol-move-up" aria-label="<?php esc_attr_e('Move up', 'manga-overlay-core'); ?>">↑</button>
                                <button type="button" class="button mol-move-down" aria-label="<?php esc_attr_e('Move down', 'manga-overlay-core'); ?>">↓</button>
                                <?php if (current_user_can('mol_manage_content')) : ?>
                                    <button type="button" class="button-link-delete mol-delete-page"><?php esc_html_e('Delete', 'manga-overlay-core'); ?></button>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="mol-upload-buttons">
                    <button type="button" class="button button-primary mol-start-upload"><?php esc_html_e('Upload queue', 'manga-overlay-core'); ?></button>
                    <?php if (current_user_can('mol_manage_content')) : ?>
                        <button type="button" class="button mol-save-order"><?php esc_html_e('Save page order', 'manga-overlay-core'); ?></button>
                    <?php endif; ?>
                </p>
            <?php else : ?>
                <p><?php esc_html_e('Create a chapter before uploading pages.', 'manga-overlay-core'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /** @return list<\WP_Post> */
    private function works(): array
    {
        $works = get_posts(array(
            'post_type' => WorkContent::POST_TYPE,
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));

        return is_array($works) ? $works : array();
    }

    /** @param list<\WP_Post> $works */
    private function workOptions(array $works): void
    {
        foreach ($works as $work) {
            printf(
                '<option value="%d">%s</option>',
                (int) $work->ID,
                esc_html($work->post_title)
            );
        }
    }

    private function statusSelect(string $name, string $selected, bool $wrapped = true): void
    {
        if ($wrapped) {
            echo '<label>' . esc_html__('Translation status', 'manga-overlay-core');
        }
        echo '<select name="' . esc_attr($name) . '">';
        foreach (array('untranslated', 'in_progress', 'completed', 'needs_review') as $status) {
            echo '<option value="' . esc_attr($status) . '" ' . selected($selected, $status, false) . '>';
            echo esc_html($status) . '</option>';
        }
        echo '</select>';
        if ($wrapped) {
            echo '</label>';
        }
    }

    /** @param list<string> $values */
    private function overrideSelect(string $name, string $label, array $values): void
    {
        echo '<label>' . esc_html($label) . '<select name="' . esc_attr($name) . '">';
        echo '<option value="">' . esc_html__('Inherit from work', 'manga-overlay-core') . '</option>';
        foreach ($values as $value) {
            echo '<option value="' . esc_attr($value) . '">' . esc_html($value) . '</option>';
        }
        echo '</select></label>';
    }
}
