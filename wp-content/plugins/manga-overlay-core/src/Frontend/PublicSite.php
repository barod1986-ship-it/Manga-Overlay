<?php
declare(strict_types=1);

namespace MOL\Frontend;

use MOL\Database\ProfileRepository;
use MOL\Database\WorkRepository;
use MOL\Domain\LibraryInput;

/** Theme-facing reads use the same services/validation as the public REST contract. */
final class PublicSite
{
	public static function library(array $query): array
	{
		global $wpdb;
		return (new WorkRepository($wpdb))->library(LibraryInput::validate($query));
	}

	public static function work(int $id): array
	{
		global $wpdb;
		return (new WorkRepository($wpdb))->detail($id);
	}

	public static function profile(string $username): array
	{
		global $wpdb;
		return (new ProfileRepository($wpdb))->find($username);
	}

	public static function chapter_url(array $chapter): string
	{
		return trailingslashit(get_permalink($chapter['work_id'])) . 'chapter/' . $chapter['slug'] . '/';
	}
}
