<?php

declare(strict_types=1);

namespace MOL\REST;

final class ProfilePresenter
{
    /**
     * @param array{
     *   stats: array{works: int, chapters: int, elements: int},
     *   recent: list<array<string, mixed>>
     * } $summary
     * @return array<string, mixed>
     */
    public static function one(\WP_User $user, array $summary): array
    {
        $userId = (int) $user->ID;
        $avatar = get_avatar_url($userId, array('size' => 192));
        $profileTag = get_user_meta($userId, 'mol_profile_tag', true);
        $recent = array_map(
            static fn (array $item): array => array(
                'work_id' => (int) $item['work_id'],
                'work_title' => null === $item['work_title'] ? null : (string) $item['work_title'],
                'chapter_id' => (int) $item['chapter_id'],
                'chapter_label' => null === $item['chapter_label'] ? null : (string) $item['chapter_label'],
                'element_count' => (int) $item['element_count'],
                'last_contributed_at' => PresenterSupport::dateTime($item['last_contributed_at']) ?? '',
            ),
            $summary['recent']
        );

        return array(
            'username' => (string) $user->user_login,
            'display_name' => (string) $user->display_name,
            'avatar_url' => is_string($avatar) && '' !== $avatar ? $avatar : null,
            'bio' => (string) $user->description,
            'profile_tag' => is_string($profileTag) && '' !== $profileTag ? $profileTag : null,
            'joined_at' => PresenterSupport::dateTime((string) $user->user_registered),
            'stats' => array(
                'works' => (int) $summary['stats']['works'],
                'chapters' => (int) $summary['stats']['chapters'],
                'elements' => (int) $summary['stats']['elements'],
            ),
            'recent_contributions' => $recent,
        );
    }
}
