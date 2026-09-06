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
		$no_subsizes = static fn () => [];
		$keep_format = static fn () => [];
		try {
			$id = wp_insert_attachment(['post_mime_type' => $info['mime'], 'post_title' => sanitize_text_field(pathinfo($file['name'], PATHINFO_FILENAME)), 'post_status' => 'inherit', 'post_parent' => $work_id], $upload['file'], $work_id, true);
			if (is_wp_error($id)) {
				throw new \RuntimeException('Could not create image attachment.');
			}
			$created['attachment_id'] = (int) $id;
			add_filter('big_image_size_threshold', $no_scale);
			add_filter('intermediate_image_sizes_advanced', $no_subsizes);
			add_filter('image_editor_output_format', $keep_format, PHP_INT_MAX);
			try {
				$metadata = wp_generate_attachment_metadata($id, $upload['file']);
			} finally {
				remove_filter('big_image_size_threshold', $no_scale);
				remove_filter('intermediate_image_sizes_advanced', $no_subsizes);
				remove_filter('image_editor_output_format', $keep_format, PHP_INT_MAX);
			}
			$metadata['sizes'] = [];
			$target = wp_image_editor_supports(['mime_type' => 'image/webp']) ? 'image/webp' : $info['mime'];
			if (get_option('mol_generate_avif', false) && wp_image_editor_supports(['mime_type' => 'image/avif'])) {
				$target = 'image/avif';
			}
			foreach ([480, 800, 1080, 1600] as $width) {
				if ($width >= $info['width']) {
					continue;
				}
				$editor = wp_get_image_editor($upload['file']);
				if (is_wp_error($editor) || is_wp_error($editor->resize($width, 0, false))) {
					continue;
				}
				// Save each derivative to its own file. A format filter on the full-size
				// Core pipeline can replace the attachment source, so it is not used here.
				$path = $editor->generate_filename('mol-' . $width, dirname($upload['file']), image_type_to_extension($target === 'image/avif' ? IMAGETYPE_AVIF : ($target === 'image/webp' ? IMAGETYPE_WEBP : ($target === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG)), false));
				$path = dirname($path) . '/' . wp_unique_filename(dirname($path), wp_basename($path));
				$saved = $editor->save($path, $target);
				if (is_wp_error($saved)) {
					$fallback = $editor->generate_filename('mol-' . $width);
					$fallback = dirname($fallback) . '/' . wp_unique_filename(dirname($fallback), wp_basename($fallback));
					$saved = $editor->save($fallback, $info['mime']);
				}
				if (!is_wp_error($saved)) {
					$created['files'][] = $saved['path'];
					$metadata['sizes']['mol-' . $width] = array_intersect_key($saved, array_flip(['file', 'width', 'height', 'mime-type', 'filesize']));
				}
			}
			wp_update_attachment_metadata($id, $metadata);
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
