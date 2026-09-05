<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('MOL_THEME_VERSION', '0.2.0');
define('MOL_THEME_DIRECTORY', get_template_directory());
define('MOL_THEME_URI', get_template_directory_uri());

require_once MOL_THEME_DIRECTORY . '/inc/query.php';
require_once MOL_THEME_DIRECTORY . '/inc/data.php';
require_once MOL_THEME_DIRECTORY . '/inc/template-tags.php';
require_once MOL_THEME_DIRECTORY . '/inc/setup.php';
