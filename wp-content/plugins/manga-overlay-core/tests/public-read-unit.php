<?php

declare(strict_types=1);

use MOL\Domain\Policy\ChapterVisibilityPolicy;
use MOL\REST\ApiException;
use MOL\REST\ChapterPresenter;
use MOL\REST\ElementPresenter;
use MOL\REST\PublicRequestValidator;

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

$molPublicCurrentUser = 0;
$molPublicCapabilities = array();

function get_current_user_id(): int
{
    global $molPublicCurrentUser;

    return $molPublicCurrentUser;
}

function current_user_can(string $capability): bool
{
    global $molPublicCapabilities;

    return ! empty($molPublicCapabilities[$capability]);
}

function user_can(int $userId, string $capability): bool
{
    global $molPublicCapabilities;

    return $userId > 0 && ! empty($molPublicCapabilities[$capability]);
}

function molPublicUnitAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function molPublicUnitExpectError(callable $operation, string $expectedCode): void
{
    try {
        $operation();
    } catch (ApiException $error) {
        molPublicUnitAssert($expectedCode === $error->errorCode(), sprintf(
            'Expected %s; received %s.',
            $expectedCode,
            $error->errorCode()
        ));
        return;
    }

    throw new RuntimeException(sprintf('Expected %s but validation succeeded.', $expectedCode));
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    throw new RuntimeException('Run Composer before the public-read unit test.');
}
require_once $autoload;

$defaults = PublicRequestValidator::library(array());
molPublicUnitAssert(1 === $defaults['page'], 'Default library page drifted.');
molPublicUnitAssert(24 === $defaults['per_page'], 'Default library page size drifted.');
molPublicUnitAssert('latest_chapter' === $defaults['sort'], 'Default library sort drifted.');
molPublicUnitAssert(array() === $defaults['genres'], 'Default genres are not empty.');

$filters = PublicRequestValidator::library(array(
    'search' => ' <b>Arabic</b> ',
    'type' => 'manhwa',
    'genre' => array('action', 'action', 'fantasy'),
    'source_lang' => 'ko',
    'work_status' => 'ongoing',
    'translation_status' => 'completed',
    'sort' => 'title_asc',
    'page' => '2',
    'per_page' => '50',
));
molPublicUnitAssert('Arabic' === $filters['search'], 'Library search was not sanitized.');
molPublicUnitAssert(array('action', 'fantasy') === $filters['genres'], 'Genres were not deduplicated.');
molPublicUnitAssert(2 === $filters['page'] && 50 === $filters['per_page'], 'Pagination strings were not normalized.');

molPublicUnitExpectError(
    static fn (): array => PublicRequestValidator::library(array('sort' => 'most_read')),
    'mol_sort_unavailable'
);
molPublicUnitExpectError(
    static fn (): array => PublicRequestValidator::library(array('translation_status' => 'translated')),
    'mol_invalid_params'
);
molPublicUnitExpectError(
    static fn (): array => PublicRequestValidator::library(array('per_page' => 101)),
    'mol_invalid_params'
);
molPublicUnitExpectError(
    static fn (): array => PublicRequestValidator::pagination(array('page' => '0')),
    'mol_invalid_params'
);
molPublicUnitAssert('ar' === PublicRequestValidator::language(null), 'Default target language drifted.');

$chapter = array(
    'id' => 7,
    'work_id' => 3,
    'chapter_label' => '1',
    'sort_order' => '1.5000',
    'title' => null,
    'slug' => 'chapter-1',
    'translation_status' => 'in_progress',
    'source_lang_override' => null,
    'reader_mode_override' => null,
    'direction_override' => 'rtl',
    'is_published' => true,
    'published_at' => '2026-09-03 10:00:00',
    'created_at' => '2026-09-01 10:00:00',
    'updated_at' => '2026-09-03 10:00:00',
);
$chapterDto = ChapterPresenter::one($chapter);
molPublicUnitAssert(is_float($chapterDto['sort_order']), 'Chapter sort_order is not a JSON number.');
molPublicUnitAssert('2026-09-03T10:00:00+00:00' === $chapterDto['published_at'], 'Chapter UTC datetime drifted.');

$elementDto = ElementPresenter::one(array(
    'id' => 9,
    'page_id' => 4,
    'target_lang' => 'ar',
    'element_type' => 'bubble',
    'x_unit' => 100,
    'y_unit' => 200,
    'w_unit' => 300,
    'h_unit' => 400,
    'rotation_mdeg' => 0,
    'z_index' => 1,
    'content' => 'مرحبا',
    'style' => array('shape' => 'ellipse'),
    'version' => 2,
    'created_by' => 1,
    'updated_by' => 2,
    'created_at' => '2026-09-01 10:00:00',
    'updated_at' => '2026-09-02 10:00:00',
));
molPublicUnitAssert(2 === $elementDto['version'], 'Element version drifted.');
molPublicUnitAssert('ellipse' === $elementDto['style']['shape'], 'Element style was not preserved.');

$visibility = new ChapterVisibilityPolicy();
molPublicUnitAssert($visibility->assertVisible($chapter), 'Published chapter was not public.');
$draft = array_merge($chapter, array('is_published' => false));
molPublicUnitExpectError(static fn (): bool => $visibility->assertVisible($draft), 'mol_not_found');
$molPublicCurrentUser = 5;
$molPublicCapabilities['mol_use_editor'] = true;
molPublicUnitAssert(! $visibility->assertVisible($draft), 'Editor draft response was marked public/cacheable.');
molPublicUnitAssert($visibility->userCanEdit(5), 'Editor capability did not grant edit access.');

echo "Manga Overlay public-read unit tests passed.\n";
