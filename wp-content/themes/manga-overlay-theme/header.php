<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="mol-skip" href="#mol-main">انتقل إلى المحتوى</a>
<header class="mol-site-header"><a class="mol-site-brand" href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(get_bloginfo('name')); ?><small>MANGA OVERLAY</small></a>
<nav aria-label="التنقل الرئيسي"><a href="<?php echo esc_url(home_url('/library/')); ?>">المكتبة</a><?php if (is_user_logged_in()) : ?><a href="<?php echo esc_url(home_url('/u/' . wp_get_current_user()->user_nicename . '/')); ?>">ملفي</a><?php else : ?><a href="<?php echo esc_url(wp_login_url()); ?>">دخول</a><?php endif; ?></nav></header>
