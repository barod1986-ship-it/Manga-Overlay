<?php

declare(strict_types=1);

namespace MOL\REST;

final class Permissions
{
    public static function authenticatedUser(): bool|\WP_Error
    {
        if (get_current_user_id() < 1) {
            return new \WP_Error(
                'mol_not_authenticated',
                'Authentication is required.',
                array('status' => 401)
            );
        }

        return true;
    }

    public static function manageContent(): bool|\WP_Error
    {
        return self::requireCapability('mol_manage_content');
    }

    public static function uploadContent(): bool|\WP_Error
    {
        return self::requireCapability('mol_upload_content');
    }

    public static function reviewTranslations(): bool|\WP_Error
    {
        return self::requireCapability('mol_review_translations');
    }

    public static function editTranslations(): bool|\WP_Error
    {
        return self::requireCapability('mol_edit_translations');
    }

    public static function deleteTranslationElements(): bool|\WP_Error
    {
        return self::requireCapability('mol_delete_translation_elements');
    }

    public static function releaseElementLock(): bool|\WP_Error
    {
        $authenticated = self::authenticatedUser();
        if (is_wp_error($authenticated)) {
            return $authenticated;
        }
        if (! current_user_can('mol_edit_translations') && ! current_user_can('mol_manage_content')) {
            return new \WP_Error(
                'mol_forbidden',
                'You are not allowed to perform this action.',
                array('status' => 403)
            );
        }

        return true;
    }

    private static function requireCapability(string $capability): bool|\WP_Error
    {
        $authenticated = self::authenticatedUser();
        if (is_wp_error($authenticated)) {
            return $authenticated;
        }
        if (! current_user_can($capability)) {
            return new \WP_Error(
                'mol_forbidden',
                'You are not allowed to perform this action.',
                array('status' => 403)
            );
        }

        return true;
    }
}
