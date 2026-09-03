<?php

declare(strict_types=1);

namespace MOL\Activation;

final class RoleManager
{
    /** @var list<string> */
    private const CANONICAL_CAPABILITIES = array(
        'mol_report_issue',
        'mol_use_editor',
        'mol_edit_translations',
        'mol_delete_translation_elements',
        'mol_review_translations',
        'mol_moderate_reports',
        'mol_upload_content',
        'mol_manage_content',
        'mol_manage_work_presets',
        'mol_manage_global_presets',
    );

    /**
     * @var array<string, array{label: string, capabilities: list<string>}>
     */
    private const ROLE_DEFINITIONS = array(
        'mol_member' => array(
            'label' => 'Manga Overlay Member',
            'capabilities' => array(
                'mol_report_issue',
            ),
        ),
        'mol_translator' => array(
            'label' => 'Manga Overlay Translator',
            'capabilities' => array(
                'mol_report_issue',
                'mol_use_editor',
                'mol_edit_translations',
                'mol_delete_translation_elements',
            ),
        ),
        'mol_moderator' => array(
            'label' => 'Manga Overlay Moderator',
            'capabilities' => array(
                'mol_report_issue',
                'mol_use_editor',
                'mol_edit_translations',
                'mol_delete_translation_elements',
                'mol_review_translations',
                'mol_moderate_reports',
                'mol_manage_work_presets',
            ),
        ),
        'mol_manager' => array(
            'label' => 'Manga Overlay Manager',
            'capabilities' => self::CANONICAL_CAPABILITIES,
        ),
    );

    public function synchronize(): bool
    {
        $synchronized = true;

        foreach (self::ROLE_DEFINITIONS as $roleSlug => $definition) {
            $role = get_role($roleSlug);
            if (null === $role) {
                add_role($roleSlug, $definition['label'], array('read' => true));
                $role = get_role($roleSlug);
            }

            if (null === $role) {
                $synchronized = false;
                continue;
            }

            $role->add_cap('read');
            foreach (self::CANONICAL_CAPABILITIES as $capability) {
                $role->remove_cap($capability);
            }
            foreach ($definition['capabilities'] as $capability) {
                $role->add_cap($capability);
            }
        }

        $administrator = get_role('administrator');
        if (null === $administrator) {
            return false;
        }

        foreach (self::CANONICAL_CAPABILITIES as $capability) {
            $administrator->add_cap($capability);
        }

        return $synchronized;
    }

    public function uninstall(): void
    {
        foreach (array_keys(self::ROLE_DEFINITIONS) as $roleSlug) {
            remove_role($roleSlug);
        }

        $administrator = get_role('administrator');
        if (null === $administrator) {
            return;
        }

        foreach (self::CANONICAL_CAPABILITIES as $capability) {
            $administrator->remove_cap($capability);
        }
    }

    /** @return list<string> */
    public static function canonicalCapabilities(): array
    {
        return self::CANONICAL_CAPABILITIES;
    }

    /** @return list<string> */
    public static function managedRoleSlugs(): array
    {
        return array_keys(self::ROLE_DEFINITIONS);
    }

    /** @return list<string> */
    public static function capabilitiesForRole(string $roleSlug): array
    {
        return self::ROLE_DEFINITIONS[$roleSlug]['capabilities'] ?? array();
    }
}
