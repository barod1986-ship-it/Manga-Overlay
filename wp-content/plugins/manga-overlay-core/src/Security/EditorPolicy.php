<?php
declare(strict_types=1);

namespace MOL\Security;

/** Progressive CSP for the plugin-owned editor document, including access errors. */
final class EditorPolicy
{
	private static ?string $nonce = null;

	public static function apply(): void
	{
		self::$nonce ??= base64_encode(random_bytes(24));
		// Append so an existing application policy remains enforced as well.
		header("Content-Security-Policy: script-src 'self' 'nonce-" . self::$nonce . "'; script-src-attr 'none'; worker-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'", false);
		header('X-Content-Type-Options: nosniff');
		header('X-Frame-Options: SAMEORIGIN');
		header('Referrer-Policy: same-origin');
		// WordPress-generated script tags are trusted server output. Never decorate
		// arbitrary HTML with a nonce or reuse the authentication/REST nonce here.
		add_filter('wp_script_attributes', [self::class, 'script_attributes']);
		add_filter('wp_inline_script_attributes', [self::class, 'script_attributes']);
	}

	public static function script_attributes(array $attributes): array
	{
		if (self::$nonce !== null) {
			$attributes['nonce'] = self::$nonce;
		}
		return $attributes;
	}
}
