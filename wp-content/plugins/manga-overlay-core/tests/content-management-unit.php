<?php

declare(strict_types=1);

use MOL\REST\ApiException;
use MOL\REST\RequestValidator;

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

function molManagementAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function molManagementExpectError(callable $operation, string $code): void
{
    try {
        $operation();
    } catch (ApiException $error) {
        molManagementAssert($code === $error->errorCode(), sprintf('Expected %s, received %s.', $code, $error->errorCode()));
        return;
    }

    throw new RuntimeException(sprintf('Expected %s but validation succeeded.', $code));
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    throw new RuntimeException('Run Composer before the content-management unit test.');
}
require_once $autoload;

$created = RequestValidator::chapterCreate(array(
    'work_id' => 42,
    'chapter_label' => '  10.5  ',
    'sort_order' => 10.5,
    'title' => '<b>Chapter title</b>',
    'translation_status' => 'in_progress',
    'reader_mode_override' => null,
    'direction_override' => 'rtl',
    'is_published' => false,
));
molManagementAssert(42 === $created['work_id'], 'work_id changed during validation.');
molManagementAssert('10.5' === $created['chapter_label'], 'chapter_label was not sanitized.');
molManagementAssert('Chapter title' === $created['title'], 'title was not sanitized.');

$patched = RequestValidator::chapterPatch(array('title' => null, 'is_published' => true));
molManagementAssert(null === $patched['title'] && true === $patched['is_published'], 'Valid patch fields changed.');
molManagementAssert('needs_review' === RequestValidator::chapterReview(array(
    'translation_status' => 'needs_review',
)), 'Review status validation failed.');
molManagementAssert(array(9, 3, 7) === RequestValidator::pageOrder(array(
    'page_ids' => array(9, 3, 7),
)), 'Page order changed during validation.');

molManagementExpectError(
    static fn (): array => RequestValidator::chapterCreate(array('work_id' => 1, 'chapter_label' => '1', 'slug' => 'client-slug')),
    'mol_invalid_params'
);
molManagementExpectError(
    static fn (): array => RequestValidator::chapterPatch(array()),
    'mol_invalid_params'
);
molManagementExpectError(
    static fn (): string => RequestValidator::chapterReview(array('translation_status' => 'in_progress')),
    'mol_invalid_params'
);
molManagementExpectError(
    static fn (): array => RequestValidator::pageOrder(array('page_ids' => array(2, 2))),
    'mol_invalid_reorder'
);
molManagementExpectError(
    static fn (): array => RequestValidator::pageOrder(array('page_ids' => array())),
    'mol_invalid_reorder'
);

echo "Manga Overlay content-management unit tests passed.\n";
