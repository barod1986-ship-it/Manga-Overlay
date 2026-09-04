<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$query = isset($args['query']) && is_array($args['query']) ? $args['query'] : mol_theme_library_query(array());
$options = isset($args['options']) && is_array($args['options']) ? $args['options'] : mol_theme_filter_options();
$selectedGenres = is_array($query['genre'] ?? null) ? $query['genre'] : array();
$sortOptions = array(
    'latest_chapter' => 'آخر فصل',
    'latest_work' => 'أحدث عمل',
    'title_asc' => 'العنوان أبجديًا',
);
if (! empty($options['most_read_available'])) {
    $sortOptions['most_read'] = 'الأكثر قراءة';
}
?>
<button class="mol-filter-backdrop" type="button" data-mol-filter-close tabindex="-1" aria-hidden="true"></button>
<aside class="mol-filter-drawer" id="mol-library-filters" aria-label="<?php esc_attr_e('فلاتر المكتبة', 'manga-overlay-theme'); ?>">
    <div class="mol-filter-drawer__heading">
        <div>
            <span class="mol-kicker"><?php esc_html_e('ضيّق النتائج', 'manga-overlay-theme'); ?></span>
            <h2><?php esc_html_e('فلاتر المكتبة', 'manga-overlay-theme'); ?></h2>
        </div>
        <button class="mol-icon-button mol-filter-drawer__close" type="button" data-mol-filter-close aria-label="<?php esc_attr_e('إغلاق الفلاتر', 'manga-overlay-theme'); ?>">
            <?php echo mol_theme_icon('close'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
        </button>
    </div>

    <form class="mol-filter-form" method="get" action="<?php echo esc_url(mol_theme_library_url()); ?>">
        <label class="mol-field" for="mol-library-search">
            <span><?php esc_html_e('العنوان أو الاسم البديل', 'manga-overlay-theme'); ?></span>
            <span class="mol-search-input">
                <?php echo mol_theme_icon('search'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                <input id="mol-library-search" name="search" type="search" value="<?php echo esc_attr((string) ($query['search'] ?? '')); ?>" placeholder="<?php esc_attr_e('ابحث في الأعمال…', 'manga-overlay-theme'); ?>">
            </span>
        </label>

        <label class="mol-field" for="mol-filter-type">
            <span><?php esc_html_e('النوع', 'manga-overlay-theme'); ?></span>
            <select id="mol-filter-type" name="type">
                <option value=""><?php esc_html_e('كل الأنواع', 'manga-overlay-theme'); ?></option>
                <?php foreach ((array) ($options['work_types'] ?? array()) as $option) : ?>
                    <option value="<?php echo esc_attr((string) $option['slug']); ?>" <?php selected((string) ($query['type'] ?? ''), (string) $option['slug']); ?>><?php echo esc_html((string) $option['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="mol-field" for="mol-filter-source-language">
            <span><?php esc_html_e('اللغة الأصلية', 'manga-overlay-theme'); ?></span>
            <select id="mol-filter-source-language" name="source_lang" dir="auto">
                <option value=""><?php esc_html_e('كل اللغات', 'manga-overlay-theme'); ?></option>
                <?php foreach ((array) ($options['source_languages'] ?? array()) as $option) : ?>
                    <option value="<?php echo esc_attr((string) $option['slug']); ?>" <?php selected((string) ($query['source_lang'] ?? ''), (string) $option['slug']); ?>><?php echo esc_html((string) $option['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="mol-field" for="mol-filter-work-status">
            <span><?php esc_html_e('حالة العمل', 'manga-overlay-theme'); ?></span>
            <select id="mol-filter-work-status" name="work_status">
                <option value=""><?php esc_html_e('كل الحالات', 'manga-overlay-theme'); ?></option>
                <?php foreach ((array) ($options['work_statuses'] ?? array()) as $option) : ?>
                    <option value="<?php echo esc_attr((string) $option['slug']); ?>" <?php selected((string) ($query['work_status'] ?? ''), (string) $option['slug']); ?>><?php echo esc_html((string) $option['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="mol-field" for="mol-filter-translation-status">
            <span><?php esc_html_e('حالة الترجمة', 'manga-overlay-theme'); ?></span>
            <select id="mol-filter-translation-status" name="translation_status">
                <option value=""><?php esc_html_e('كل حالات الترجمة', 'manga-overlay-theme'); ?></option>
                <?php foreach (mol_theme_translation_status_labels() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($query['translation_status'] ?? ''), $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <?php if (! empty($options['genres'])) : ?>
            <fieldset class="mol-genre-fieldset">
                <legend><?php esc_html_e('التصنيفات', 'manga-overlay-theme'); ?></legend>
                <div class="mol-checkbox-list">
                    <?php foreach ((array) $options['genres'] as $option) : ?>
                        <?php $genreId = 'mol-genre-' . sanitize_html_class((string) $option['slug']); ?>
                        <label for="<?php echo esc_attr($genreId); ?>">
                            <input id="<?php echo esc_attr($genreId); ?>" name="genre[]" type="checkbox" value="<?php echo esc_attr((string) $option['slug']); ?>" <?php checked(in_array((string) $option['slug'], $selectedGenres, true)); ?>>
                            <span dir="auto"><?php echo esc_html((string) $option['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <div class="mol-filter-form__row">
            <label class="mol-field" for="mol-filter-sort">
                <span><?php esc_html_e('الترتيب', 'manga-overlay-theme'); ?></span>
                <select id="mol-filter-sort" name="sort">
                    <?php if ('most_read' === ($query['sort'] ?? '') && empty($options['most_read_available'])) : ?>
                        <option value="most_read" selected disabled><?php esc_html_e('الأكثر قراءة — غير متاح', 'manga-overlay-theme'); ?></option>
                    <?php endif; ?>
                    <?php foreach ($sortOptions as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected((string) ($query['sort'] ?? ''), $value); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="mol-field" for="mol-filter-per-page">
                <span><?php esc_html_e('في الصفحة', 'manga-overlay-theme'); ?></span>
                <select id="mol-filter-per-page" name="per_page">
                    <?php foreach (array(12, 24, 48) as $amount) : ?>
                        <option value="<?php echo esc_attr((string) $amount); ?>" <?php selected((int) ($query['per_page'] ?? 12), $amount); ?>><?php echo esc_html((string) $amount); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="mol-filter-form__actions">
            <button class="mol-button mol-button--primary" type="submit"><?php esc_html_e('اعرض النتائج', 'manga-overlay-theme'); ?></button>
            <a class="mol-button mol-button--quiet" href="<?php echo esc_url(mol_theme_library_url()); ?>"><?php esc_html_e('إعادة الضبط', 'manga-overlay-theme'); ?></a>
        </div>
    </form>
</aside>

