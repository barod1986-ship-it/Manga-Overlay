<?php get_header(); ?>
<main id="mol-main" class="mol-site-main">
<?php if (!class_exists('MOL\\Frontend\\PublicSite')) : ?>
<p class="mol-empty">المكتبة غير متاحة الآن.</p>
<?php else : ?>
<h1><?php echo esc_html(get_the_archive_title()); ?></h1>
<?php while (have_posts()) : the_post(); ?><article class="mol-content"><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?></article><?php endwhile; ?>
<?php endif; ?></main><?php get_footer(); ?>
