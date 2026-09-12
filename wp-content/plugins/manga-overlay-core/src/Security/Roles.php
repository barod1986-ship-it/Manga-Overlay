<?php
declare(strict_types=1);

namespace MOL\Security;

final class Roles
{
	public const VERSION = '1';
	public const CAPABILITIES = [
		'mol_report_issue', 'mol_use_editor', 'mol_edit_translations', 'mol_delete_translation_elements',
		'mol_review_translations', 'mol_moderate_reports', 'mol_upload_content', 'mol_manage_content',
		'mol_manage_work_presets', 'mol_manage_global_presets',
	];

	public static function install(): void
	{
		if (get_option('mol_roles_version') === self::VERSION) {
			return;
		}
		$member = ['read', 'mol_report_issue'];
		$translator = [...$member, 'mol_use_editor', 'mol_edit_translations', 'mol_delete_translation_elements'];
		$moderator = [...$translator, 'mol_review_translations', 'mol_moderate_reports', 'mol_manage_work_presets'];
		$definitions = [
			'mol_member' => ['عضو', $member],
			'mol_translator' => ['مترجم', $translator],
			'mol_moderator' => ['مشرف', $moderator],
			'mol_manager' => ['مدير المحتوى', ['read', ...self::CAPABILITIES]],
		];
		foreach ($definitions as $id => [$label, $capabilities]) {
			add_role($id, $label, array_fill_keys($capabilities, true));
			$role = get_role($id);
			if (!$role) {
				throw new \RuntimeException('Could not initialize MOL role.');
			}
			foreach ($capabilities as $capability) {
				$role->add_cap($capability);
			}
		}
		$administrator = get_role('administrator');
		if ($administrator) {
			foreach (self::CAPABILITIES as $capability) {
				$administrator->add_cap($capability);
			}
		}
		update_option('mol_roles_version', self::VERSION, false);
	}
}
