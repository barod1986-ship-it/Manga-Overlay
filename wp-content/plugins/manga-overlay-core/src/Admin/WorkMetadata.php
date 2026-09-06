<?php
declare(strict_types=1);

namespace MOL\Admin;

use MOL\Content\WorkType;

final class WorkMetadata
{
	public static function register(): void
	{
		add_meta_box('mol-work-settings', 'بيانات القراءة والأسماء البديلة', [self::class, 'render'], 'mol_work', 'normal', 'default');
	}

	public static function render(\WP_Post $post): void
	{
		wp_nonce_field('mol_work_metadata', 'mol_work_metadata_nonce');
		$titles = get_post_meta($post->ID, '_mol_alt_titles', true);
		?>
		<p><label for="mol-alt-titles">الأسماء البديلة — اسم في كل سطر</label><br><textarea id="mol-alt-titles" name="mol_alt_titles" rows="4" class="widefat"><?php echo esc_textarea(implode("\n", is_array($titles) ? $titles : [])); ?></textarea></p>
		<p><label for="mol-reader-mode">طريقة القراءة الافتراضية</label> <select id="mol-reader-mode" name="mol_default_reader_mode"><option value="webtoon" <?php selected(get_post_meta($post->ID, '_mol_default_reader_mode', true), 'webtoon'); ?>>تمرير عمودي</option><option value="paged" <?php selected(get_post_meta($post->ID, '_mol_default_reader_mode', true), 'paged'); ?>>صفحات</option></select></p>
		<p><label for="mol-reading-direction">اتجاه القراءة</label> <select id="mol-reading-direction" name="mol_reading_direction"><option value="rtl" <?php selected(get_post_meta($post->ID, '_mol_reading_direction', true), 'rtl'); ?>>من اليمين إلى اليسار</option><option value="ltr" <?php selected(get_post_meta($post->ID, '_mol_reading_direction', true), 'ltr'); ?>>من اليسار إلى اليمين</option></select></p>
		<?php
	}

	public static function save(int $post_id): void
	{
		if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !current_user_can('mol_manage_content') || !isset($_POST['mol_work_metadata_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mol_work_metadata_nonce'])), 'mol_work_metadata')) {
			return;
		}
		if (isset($_POST['mol_alt_titles']) && is_string($_POST['mol_alt_titles'])) {
			update_post_meta($post_id, '_mol_alt_titles', WorkType::sanitize_titles(preg_split('/\R/u', wp_unslash($_POST['mol_alt_titles'])) ?: []));
		}
		foreach (['mol_default_reader_mode' => ['webtoon', 'paged'], 'mol_reading_direction' => ['rtl', 'ltr']] as $key => $allowed) {
			if (isset($_POST[$key]) && in_array($_POST[$key], $allowed, true)) {
				update_post_meta($post_id, '_' . $key, $_POST[$key]);
			}
		}
	}
}
