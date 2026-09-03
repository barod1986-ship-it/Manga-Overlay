<?php

declare(strict_types=1);

namespace MOL\REST;

final class PagePresenter
{
    /** @param array<string, mixed> $page @return array<string, mixed> */
    public static function one(array $page): array
    {
        $attachmentId = (int) $page['attachment_id'];
        $url = wp_get_attachment_url($attachmentId);
        $srcset = wp_get_attachment_image_srcset($attachmentId, 'full');
        $sizes = wp_get_attachment_image_sizes($attachmentId, 'full');
        $alt = get_post_meta($attachmentId, '_wp_attachment_image_alt', true);

        return array(
            'id' => (int) $page['id'],
            'chapter_id' => (int) $page['chapter_id'],
            'page_index' => (int) $page['page_index'],
            'natural_width' => (int) $page['natural_width'],
            'natural_height' => (int) $page['natural_height'],
            'image' => array(
                'attachment_id' => $attachmentId,
                'url' => is_string($url) ? $url : '',
                'width' => (int) $page['natural_width'],
                'height' => (int) $page['natural_height'],
                'srcset' => is_string($srcset) && '' !== $srcset ? $srcset : null,
                'sizes' => is_string($sizes) && '' !== $sizes ? $sizes : null,
                'alt' => is_string($alt) && '' !== $alt ? $alt : null,
            ),
        );
    }

    /** @param list<array<string, mixed>> $pages @return list<array<string, mixed>> */
    public static function many(array $pages): array
    {
        return array_map(self::one(...), $pages);
    }
}
