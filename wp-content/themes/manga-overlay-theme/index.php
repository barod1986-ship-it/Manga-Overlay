<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main id="mol-content" class="mol-shell mol-default-main">
    <header class="mol-default-heading">
        <span class="mol-kicker"><?php esc_html_e('Manga Overlay', 'manga-overlay-theme'); ?></span>
        <h1><?php echo esc_html(is_home() ? get_bloginfo('name') : get_the_archive_title()); ?></h1>
    </header>
    <?php if (have_posts()) : ?>
        <div class="mol-post-list">
            <?php while (have_posts()) : ?>
                <?php the_post(); ?>
                <article <?php post_class('mol-post-summary'); ?>>
                    <h2 dir="auto"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="mol-prose"><?php the_excerpt(); ?></div>
                </article>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <div class="mol-empty-state"><h2><?php esc_html_e('لا يوجد محتوى بعد', 'manga-overlay-theme'); ?></h2></div>
    <?php endif; ?>
</main>
<?php
get_footer();

