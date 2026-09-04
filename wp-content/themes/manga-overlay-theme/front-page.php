<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$latest = mol_theme_library_data(array(
    'sort' => 'latest_chapter',
    'page' => 1,
    'per_page' => 6,
));
$latestWorks = is_array($latest['data'] ?? null) ? $latest['data'] : array();
$filterOptions = mol_theme_filter_options();
$genres = array_slice((array) ($filterOptions['genres'] ?? array()), 0, 10);

get_header();
?>
<main id="mol-content">
    <section class="mol-home-hero">
        <div class="mol-shell mol-home-hero__grid">
            <div class="mol-home-hero__copy">
                <span class="mol-kicker">READ THE ORIGINAL · شاهد الترجمة</span>
                <h1><?php esc_html_e('الصفحة الأصلية تبقى. العربية تأتي فوقها.', 'manga-overlay-theme'); ?></h1>
                <p><?php esc_html_e('اقرأ المانجا والمانهوا والكوميك كما رُسمت، وأظهر الترجمة العربية أو أخفها متى شئت.', 'manga-overlay-theme'); ?></p>
                <form class="mol-hero-search" method="get" action="<?php echo esc_url(mol_theme_library_url()); ?>" role="search">
                    <label class="screen-reader-text" for="mol-home-search"><?php esc_html_e('ابحث في المكتبة', 'manga-overlay-theme'); ?></label>
                    <?php echo mol_theme_icon('search'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                    <input id="mol-home-search" name="search" type="search" placeholder="<?php esc_attr_e('اسم العمل أو عنوان بديل…', 'manga-overlay-theme'); ?>">
                    <button type="submit"><?php esc_html_e('ابحث', 'manga-overlay-theme'); ?></button>
                </form>
                <?php if (! empty($genres)) : ?>
                    <div class="mol-hero-genres" aria-label="<?php esc_attr_e('تصنيفات سريعة', 'manga-overlay-theme'); ?>">
                        <?php foreach (array_slice($genres, 0, 5) as $genre) : ?>
                            <a href="<?php echo esc_url(mol_theme_library_url(array('genre' => array((string) $genre['slug'])))); ?>" dir="auto"><?php echo esc_html((string) $genre['name']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mol-home-hero__manifesto" aria-label="<?php esc_attr_e('كيف تعمل المنصة', 'manga-overlay-theme'); ?>">
                <span class="mol-home-hero__issue">MOL / 001</span>
                <div class="mol-home-hero__panel">
                    <?php echo mol_theme_icon('book'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?>
                    <strong><?php esc_html_e('صورة أصلية', 'manga-overlay-theme'); ?></strong>
                    <small><?php esc_html_e('لا نكتب فوق الملف', 'manga-overlay-theme'); ?></small>
                </div>
                <div class="mol-home-hero__plus" aria-hidden="true">+</div>
                <div class="mol-home-hero__panel mol-home-hero__panel--accent">
                    <span aria-hidden="true">ع</span>
                    <strong><?php esc_html_e('طبقة عربية', 'manga-overlay-theme'); ?></strong>
                    <small><?php esc_html_e('تظهر وتختفي فورًا', 'manga-overlay-theme'); ?></small>
                </div>
            </div>
        </div>
    </section>

    <section class="mol-home-section mol-shell" aria-labelledby="mol-latest-heading">
        <header class="mol-section-heading">
            <div>
                <span class="mol-kicker"><?php esc_html_e('وصل حديثًا', 'manga-overlay-theme'); ?></span>
                <h2 id="mol-latest-heading"><?php esc_html_e('آخر تحديثات الفصول', 'manga-overlay-theme'); ?></h2>
            </div>
            <a class="mol-text-link" href="<?php echo esc_url(mol_theme_library_url()); ?>"><?php esc_html_e('كل المكتبة', 'manga-overlay-theme'); ?> <?php echo mol_theme_icon('arrow-end'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG. ?></a>
        </header>

        <?php if (is_array($latest['error'] ?? null)) : ?>
            <div class="mol-notice mol-notice--error" role="status"><?php esc_html_e('تعذر تحميل الأعمال الحديثة الآن.', 'manga-overlay-theme'); ?></div>
        <?php elseif (empty($latestWorks)) : ?>
            <div class="mol-empty-state mol-empty-state--compact">
                <h3><?php esc_html_e('المكتبة بانتظار أول عمل', 'manga-overlay-theme'); ?></h3>
                <p><?php esc_html_e('ستظهر أحدث الفصول هنا بعد نشرها.', 'manga-overlay-theme'); ?></p>
            </div>
        <?php else : ?>
            <div class="mol-work-grid mol-work-grid--home">
                <?php foreach ($latestWorks as $index => $work) : ?>
                    <?php if (is_array($work)) : ?>
                        <?php get_template_part('template-parts/work-card', null, array('work' => $work, 'priority' => $index < 2)); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (! empty($genres)) : ?>
        <section class="mol-genre-band">
            <div class="mol-shell mol-genre-band__inner">
                <div>
                    <span class="mol-kicker"><?php esc_html_e('اختر مزاجك', 'manga-overlay-theme'); ?></span>
                    <h2><?php esc_html_e('تصفح بالتصنيف', 'manga-overlay-theme'); ?></h2>
                </div>
                <div class="mol-genre-cloud">
                    <?php foreach ($genres as $genre) : ?>
                        <a href="<?php echo esc_url(mol_theme_library_url(array('genre' => array((string) $genre['slug'])))); ?>" dir="auto"><?php echo esc_html((string) $genre['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
get_footer();

