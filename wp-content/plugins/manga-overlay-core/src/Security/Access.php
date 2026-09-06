<?php
declare(strict_types=1);

namespace MOL\Security;

use MOL\Domain\Fault;

final class Access
{
	public static function authenticated(\WP_REST_Request $request): bool
	{
		return is_user_logged_in() && (bool) wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
	}

	public static function require(\WP_REST_Request $request, string $capability): true
	{
		if (!is_user_logged_in()) {
			throw new Fault('mol_not_authenticated', 'سجّل الدخول للمتابعة.', 401);
		}
		if (!self::authenticated($request) || ($capability !== 'authenticated_user' && !current_user_can($capability))) {
			throw new Fault('mol_forbidden', 'لا تملك صلاحية هذه العملية.', 403);
		}
		return true;
	}

	public static function capability(string $capability): void
	{
		if (!current_user_can($capability)) {
			throw new Fault('mol_forbidden', 'لا تملك صلاحية هذه العملية.', 403);
		}
	}
}
