<?php
declare(strict_types=1);

namespace MOL\Media;

final class ImageResource
{
	public static function from_attachment(int $id, int $fallback_width = 1, int $fallback_height = 1): array
	{
		$source = wp_get_attachment_image_src($id, 'full');
		return [
			'attachment_id' => $id > 0 ? $id : null,
			'url' => $source ? $source[0] : '',
			'width' => $source ? (int) $source[1] : max(1, $fallback_width),
			'height' => $source ? (int) $source[2] : max(1, $fallback_height),
			'srcset' => wp_get_attachment_image_srcset($id, 'full') ?: null,
			'sizes' => wp_get_attachment_image_sizes($id, 'full') ?: null,
			'alt' => get_post_meta($id, '_wp_attachment_image_alt', true) ?: null,
		];
	}
}
