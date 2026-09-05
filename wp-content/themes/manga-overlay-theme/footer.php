<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<footer class="mol-site-footer">
    <div class="mol-shell mol-site-footer__grid">
        <div>
            <span class="mol-site-footer__eyebrow">MANGA OVERLAY</span>
            <h2 dir="auto"><?php bloginfo('name'); ?></h2>
            <p><?php esc_html_e('مكتبة قصص مصورة تُبقي الصفحة الأصلية كما هي، وتمنح الترجمة العربية طبقتها المستقلة.', 'manga-overlay-theme'); ?></p>
        </div>
        <nav aria-label="<?php esc_attr_e('روابط التذييل', 'manga-overlay-theme'); ?>">
            <?php if (has_nav_menu('footer')) : ?>
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'container' => false,
                    'menu_class' => 'mol-footer-links',
                    'fallback_cb' => false,
                    'depth' => 1,
                ));
                ?>
            <?php else : ?>
                <ul class="mol-footer-links">
                    <li><a href="<?php echo esc_url(mol_theme_library_url()); ?>"><?php esc_html_e('تصفح المكتبة', 'manga-overlay-theme'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('الرئيسية', 'manga-overlay-theme'); ?></a></li>
                </ul>
            <?php endif; ?>
        </nav>
    </div>
    <div class="mol-shell mol-site-footer__base">
        <span>&copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?></span>
        <span><?php esc_html_e('واجهة عربية RTL', 'manga-overlay-theme'); ?></span>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

