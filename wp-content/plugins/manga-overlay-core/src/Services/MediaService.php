<?php

declare(strict_types=1);

namespace MOL\Services;

use MOL\REST\ApiException;
use RuntimeException;

final class MediaService
{
    public const WEBP_META_KEY = '_mol_webp_derivative';

    /**
     * @param array<string, mixed> $file
     * @return array{file: array<string, mixed>, mime: string, width: int, height: int, digest: string}
     */
    public function inspect(array $file): array
    {
        foreach (array('name', 'tmp_name', 'error', 'size') as $field) {
            if (! array_key_exists($field, $file)) {
                throw ApiException::invalidParams('A complete image upload is required.');
            }
        }

        $uploadError = (int) $file['error'];
        if (in_array($uploadError, array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true)) {
            throw $this->payloadTooLarge();
        }
        if (UPLOAD_ERR_OK !== $uploadError) {
            throw ApiException::invalidParams('The image upload did not complete successfully.');
        }

        $temporaryPath = (string) $file['tmp_name'];
        if ('' === $temporaryPath || ! is_file($temporaryPath) || ! is_readable($temporaryPath)) {
            throw ApiException::invalidParams('The uploaded image payload is unavailable.');
        }
        $actualBytes = filesize($temporaryPath);
        if (false === $actualBytes) {
            throw ApiException::invalidParams('The uploaded image size could not be read.');
        }
        $maxBytes = max(1, (int) apply_filters('mol_max_page_upload_bytes', wp_max_upload_size()));
        if ($actualBytes > $maxBytes || (int) $file['size'] > $maxBytes) {
            throw $this->payloadTooLarge();
        }

        $name = sanitize_file_name(wp_basename((string) $file['name']));
        if ('' === $name) {
            throw ApiException::invalidParams('The uploaded image filename is invalid.');
        }
        $allowedMimes = $this->allowedMimes();
        $checked = wp_check_filetype_and_ext($temporaryPath, $name, $allowedMimes);
        $imageInfo = wp_getimagesize($temporaryPath);
        $checkedMime = is_array($checked) && is_string($checked['type'] ?? null) ? $checked['type'] : '';
        $decodedMime = is_array($imageInfo) && is_string($imageInfo['mime'] ?? null) ? $imageInfo['mime'] : '';
        if ('' === $checkedMime || $checkedMime !== $decodedMime || ! in_array($decodedMime, $allowedMimes, true)) {
            throw $this->unsupportedMedia();
        }

        $editor = wp_get_image_editor($temporaryPath);
        if (is_wp_error($editor)) {
            throw $this->unsupportedMedia();
        }
        $size = $editor->get_size();
        if (! is_array($size) || empty($size['width']) || empty($size['height'])) {
            throw $this->unsupportedMedia();
        }
        $width = (int) $size['width'];
        $height = (int) $size['height'];
        $maxWidth = max(1, (int) apply_filters('mol_max_page_width', 50000));
        $maxHeight = max(1, (int) apply_filters('mol_max_page_height', 50000));
        $maxPixels = max(1, (int) apply_filters('mol_max_page_pixels', 100000000));
        if ($width > $maxWidth || $height > $maxHeight || $width * $height > $maxPixels) {
            throw $this->payloadTooLarge();
        }

        $digest = hash_file('sha256', $temporaryPath);
        if (! is_string($digest)) {
            throw new RuntimeException('The uploaded image could not be fingerprinted.');
        }

        return array(
            'file' => array(
                'name' => $name,
                'type' => $decodedMime,
                'tmp_name' => $temporaryPath,
                'error' => UPLOAD_ERR_OK,
                'size' => $actualBytes,
            ),
            'mime' => $decodedMime,
            'width' => $width,
            'height' => $height,
            'digest' => $digest,
        );
    }

    /**
     * @param array{file: array<string, mixed>, mime: string, width: int, height: int, digest: string} $inspected
     * @return array{attachment_id: int, width: int, height: int}
     */
    public function store(array $inspected): array
    {
        $this->loadWordPressMediaFunctions();
        $attachmentId = media_handle_sideload(
            $inspected['file'],
            0,
            null,
            array('post_mime_type' => $inspected['mime'])
        );
        if (is_wp_error($attachmentId)) {
            throw new RuntimeException('WordPress could not persist the uploaded page image.');
        }
        $attachmentId = (int) $attachmentId;

        $metadata = wp_get_attachment_metadata($attachmentId);
        $width = is_array($metadata) && ! empty($metadata['width'])
            ? (int) $metadata['width']
            : $inspected['width'];
        $height = is_array($metadata) && ! empty($metadata['height'])
            ? (int) $metadata['height']
            : $inspected['height'];
        $this->generateWebpDerivative($attachmentId, $inspected['mime']);

        return array('attachment_id' => $attachmentId, 'width' => $width, 'height' => $height);
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $derivative = get_post_meta($attachmentId, self::WEBP_META_KEY, true);
        if (is_array($derivative) && is_string($derivative['path'] ?? null) && is_file($derivative['path'])) {
            wp_delete_file($derivative['path']);
        }
        wp_delete_attachment($attachmentId, true);
    }

    /** @return array<string, string> */
    public function allowedMimes(): array
    {
        $mimes = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        );
        if (function_exists('wp_image_editor_supports')
            && wp_image_editor_supports(array('mime_type' => 'image/avif'))
        ) {
            $mimes['avif'] = 'image/avif';
        }

        return $mimes;
    }

    private function generateWebpDerivative(int $attachmentId, string $sourceMime): void
    {
        if ('image/webp' === $sourceMime
            || ! apply_filters('mol_generate_webp_derivative', true, $attachmentId)
            || ! function_exists('wp_image_editor_supports')
            || ! wp_image_editor_supports(array('mime_type' => 'image/webp'))
        ) {
            return;
        }

        $sourcePath = get_attached_file($attachmentId);
        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            return;
        }
        $editor = wp_get_image_editor($sourcePath);
        if (is_wp_error($editor)) {
            return;
        }

        $directory = dirname($sourcePath);
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME) . '.webp';
        $destination = trailingslashit($directory) . wp_unique_filename($directory, $filename);
        $saved = $editor->save($destination, 'image/webp');
        if (is_wp_error($saved) || ! is_array($saved) || ! is_string($saved['path'] ?? null)) {
            if (is_file($destination)) {
                wp_delete_file($destination);
            }
            return;
        }

        update_post_meta($attachmentId, self::WEBP_META_KEY, array(
            'path' => $saved['path'],
            'mime_type' => 'image/webp',
            'width' => (int) ($saved['width'] ?? 0),
            'height' => (int) ($saved['height'] ?? 0),
        ));
    }

    private function loadWordPressMediaFunctions(): void
    {
        foreach (array('file.php', 'media.php', 'image.php') as $include) {
            $path = ABSPATH . 'wp-admin/includes/' . $include;
            if (is_readable($path)) {
                require_once $path;
            }
        }
    }

    private function unsupportedMedia(): ApiException
    {
        return new ApiException('mol_unsupported_media', 'Unsupported media type.', 415);
    }

    private function payloadTooLarge(): ApiException
    {
        return new ApiException('mol_payload_too_large', 'The uploaded payload exceeds the allowed size.', 413);
    }
}
