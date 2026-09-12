<?php
declare(strict_types=1);

function mol_get_work_chapters(int $work_id, array $args = []): array
{
	global $wpdb;
	return (new MOL\Services\PublicReader($wpdb))->work_chapters($work_id, $args);
}

function mol_get_chapter(int $chapter_id): ?array
{
	global $wpdb;
	try {
		return (new MOL\Services\PublicReader($wpdb))->chapter($chapter_id);
	} catch (MOL\Domain\Fault $error) {
		return null;
	}
}

function mol_get_chapter_pages(int $chapter_id): array
{
	global $wpdb;
	try {
		return (new MOL\Services\PublicReader($wpdb))->pages($chapter_id);
	} catch (MOL\Domain\Fault $error) {
		return [];
	}
}

function mol_get_page_elements(int $page_id, string $lang = 'ar'): array
{
	global $wpdb;
	try {
		return (new MOL\Services\PublicReader($wpdb))->page_elements($page_id, $lang);
	} catch (MOL\Domain\Fault $error) {
		return [];
	}
}

function mol_get_chapter_elements(int $chapter_id, string $lang = 'ar'): array
{
	global $wpdb;
	try {
		return (new MOL\Services\PublicReader($wpdb))->chapter_elements($chapter_id, $lang);
	} catch (MOL\Domain\Fault $error) {
		return [];
	}
}

function mol_get_chapter_contributors(int $chapter_id): array
{
	global $wpdb;
	try {
		return (new MOL\Services\PublicReader($wpdb))->contributors($chapter_id);
	} catch (MOL\Domain\Fault $error) {
		return [];
	}
}

function mol_user_can_edit_chapter(int $user_id, int $chapter_id): bool
{
	global $wpdb;
	return user_can($user_id, 'mol_use_editor') && user_can($user_id, 'mol_edit_translations') && (new MOL\Database\ChapterRepository($wpdb))->find($chapter_id) !== null;
}
