<?php
declare(strict_types=1);

namespace MOL\Media;

use MOL\Domain\Fault;

final class MediaService
{
	public static function capabilities(): array
	{
		$mimes = ['image/jpeg', 'image/png', 'image/webp'];
		if (wp_image_editor_supports(['mime_type' => 'image/avif'])) {
			$mimes[] = 'image/avif';
		}
		$formats = ['jpeg'];
		if (wp_image_editor_supports(['mime_type' => 'image/webp'])) {
			$formats[] = 'webp';
		}
		// AVIF derivatives remain opt-in after server quality/CPU measurement.
		if (get_option('mol_generate_avif', false) && in_array('image/avif', $mimes, true)) {
			$formats[] = 'avif';
		}
		return ['upload_mime_types' => $mimes, 'derived_image_formats' => $formats, 'most_read_available' => false];
	}

	public function inspect(array $file): array
	{
		$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
		if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
			throw new Fault('mol_payload_too_large', 'الصورة أكبر من حد الرفع.', 413);
		}
		if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_file($file['tmp_name']) || !is_string($file['name'] ?? null)) {
			throw Fault::invalid('اختر ملف صورة واحدًا صالحًا.');
		}
		$bytes = filesize($file['tmp_name']);
		$maximum = min(wp_max_upload_size(), max(1, (int) get_option('mol_upload_max_bytes', 20 * MB_IN_BYTES)));
		if ($bytes > $maximum) {
			throw new Fault('mol_payload_too_large', 'الصورة أكبر من حد الرفع.', 413);
		}
		$info = wp_getimagesize($file['tmp_name']);
		$mimes = self::mime_map();
		$checked = wp_check_filetype_and_ext($file['tmp_name'], sanitize_file_name($file['name']), $mimes);
		if (!$info || !$checked['ext'] || !$checked['type'] || ($info['mime'] ?? '') !== $checked['type'] || !in_array($checked['type'], array_values($mimes), true)) {
			throw new Fault('mol_unsupported_media', 'استخدم صورة JPEG أو PNG أو WebP مدعومة.', 415);
		}
		if ($info[0] < 1 || $info[1] < 1 || $info[0] > (int) get_option('mol_image_max_width', 10000) || $info[1] > (int) get_option('mol_image_max_height', 40000) || $info[0] * $info[1] > (int) get_option('mol_image_max_pixels', 32000000)) {
			throw new Fault('mol_payload_too_large', 'أبعاد الصورة أكبر من حدود المعالجة.', 413);
		}
		$editor = wp_get_image_editor($file['tmp_name']);
		if (is_wp_error($editor)) {
			throw new Fault('mol_unsupported_media', 'تعذر فك الصورة في محرر صور الخادم.', 415);
		}
		unset($editor);
		return ['width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => $checked['type'], 'hash' => hash_file('sha256', $file['tmp_name'])];
	}

	/** Accepts an already inspected, trusted local upload path from the REST adapter. */
	public function create(array $file, array $info, int $work_id): array
	{
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$file['name'] = sanitize_file_name($file['name']);
		$file['type'] = $info['mime'];
		$file['size'] = filesize($file['tmp_name']);
		$upload = wp_handle_sideload($file, ['test_form' => false, 'mimes' => self::mime_map()]);
		if (isset($upload['error'])) {
			throw new Fault('mol_unsupported_media', 'تعذر تسجيل الصورة المرفوعة.', 415);
		}
		$created = ['attachment_id' => 0, 'files' => [$upload['file']], 'width' => $info['width'], 'height' => $info['height']];
		$no_scale = static fn () => false;
		$size_filter = static function (): array {
			$sizes = [];
			foreach ([480, 800, 1080, 1600] as $width) {
				$sizes['mol-' . $width] = ['width' => $width, 'height' => 0, 'crop' => false];
			}
			return $sizes;
		};
		$output_format = static function (array $formats, string $filename, string $mime): array {
			if (in_array($mime, ['image/jpeg', 'image/png', 'image/avif'], true) && wp_image_editor_supports(['mime_type' => 'image/webp'])) {
				$formats[$mime] = get_option('mol_generate_avif', false) && wp_image_editor_supports(['mime_type' => 'image/avif']) ? 'image/avif' : 'image/webp';
			}
			return $formats;
		};
		try {
			$id = wp_insert_attachment(['post_mime_type' => $info['mime'], 'post_title' => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)), 'post_status' => 'inherit', 'post_parent' => $work_id], $upload['file'], $work_id, true);
			if (is_wp_error($id)) {
				throw new \RuntimeException('Could not create image attachment.');
			}
			$created['attachment_id'] = (int) $id;
			add_filter('big_image_size_threshold', $no_scale);
			add_filter('intermediate_image_sizes_advanced', $size_filter);
			add_filter('image_editor_output_format', $output_format, 10, 3);
			try {
				$metadata = wp_generate_attachment_metadata($id, $upload['file']);
				remove_filter('image_editor_output_format', $output_format, 10);
				if (empty($metadata['sizes']) && $info['width'] > 480) {
					$metadata = wp_generate_attachment_metadata($id, $upload['file']);
				}
			} finally {
				remove_filter('big_image_size_threshold', $no_scale);
				remove_filter('intermediate_image_sizes_advanced', $size_filter);
				remove_filter('image_editor_output_format', $output_format, 10);
			}
			wp_update_attachment_metadata($id, $metadata);
			foreach ($metadata['sizes'] ?? [] as $size) {
				$created['files'][] = dirname($upload['file']) . '/' . wp_basename($size['file']);
			}
			return $created;
		} catch (\Throwable $error) {
			$this->cleanup($created);
			throw $error;
		}
	}

	public function cleanup(array $created): void
	{
		if ($created['attachment_id']) {
			wp_delete_attachment($created['attachment_id'], true);
			clean_post_cache($created['attachment_id']);
		}
		foreach ($created['files'] as $file) {
			if (is_file($file)) {
				wp_delete_file($file);
			}
		}
	}

	private static function mime_map(): array
	{
		$mimes = ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
		if (in_array('image/avif', self::capabilities()['upload_mime_types'], true)) {
			$mimes['avif'] = 'image/avif';
		}
		return $mimes;
	}
}
