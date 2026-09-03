<?php

declare(strict_types=1);

namespace MOL\REST;

final class ContributorPresenter
{
    /**
     * @param list<array{user_id: int, element_count: int}> $contributors
     * @return list<array<string, mixed>>
     */
    public static function many(array $contributors): array
    {
        return array_map(
            static function (array $contributor): array {
                $userId = (int) $contributor['user_id'];
                $user = get_userdata($userId);
                if (! $user instanceof \WP_User) {
                    return array(
                        'user_id' => $userId,
                        'username' => 'deleted-user-' . $userId,
                        'display_name' => __('Deleted user', 'manga-overlay-core'),
                        'avatar_url' => null,
                        'profile_tag' => null,
                        'element_count' => (int) $contributor['element_count'],
                    );
                }

                $avatar = get_avatar_url($userId, array('size' => 96));
                $profileTag = get_user_meta($userId, 'mol_profile_tag', true);

                return array(
                    'user_id' => $userId,
                    'username' => (string) $user->user_login,
                    'display_name' => (string) $user->display_name,
                    'avatar_url' => is_string($avatar) && '' !== $avatar ? $avatar : null,
                    'profile_tag' => is_string($profileTag) && '' !== $profileTag ? $profileTag : null,
                    'element_count' => (int) $contributor['element_count'],
                );
            },
            $contributors
        );
    }
}
