<?php

declare(strict_types=1);

use MOL\REST\ApiException;
use MOL\REST\RequestValidator;
use MOL\Domain\ElementStyles;

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

$elementCreate = RequestValidator::elementCreate(array(
    'page_id' => 7,
    'target_lang' => 'ar',
    'element_type' => 'bubble',
    'x_unit' => 100_000,
    'y_unit' => 120_000,
    'w_unit' => 300_000,
    'h_unit' => 180_000,
    'content' => "</script>\nنص آمن",
    'style' => array('tail' => array('enabled' => false)),
));
molManagementAssert('</script>' === substr($elementCreate['content'], 0, 9), 'Element content was treated as HTML.');
molManagementAssert(0 === $elementCreate['rotation_mdeg'], 'Element rotation default drifted.');
molManagementAssert(null === $elementCreate['preset_id'], 'Element preset default drifted.');

$resolvedBubble = ElementStyles::resolve('bubble', $elementCreate['style']);
molManagementAssert('cairo' === $resolvedBubble['fontId'], 'Bubble Base Style font drifted.');
molManagementAssert(false === $resolvedBubble['tail']['enabled'], 'Nested style override was not applied.');
molManagementAssert(80_000 === $resolvedBubble['tail']['lengthUnit'], 'Nested style override discarded Base Style siblings.');

$elementPatch = RequestValidator::elementPatch(array(
    'element_type' => 'sfx',
    'style' => array('strokeColor' => '#CC0000'),
    'content' => 'طَقّ!',
));
molManagementAssert('#CC0000' === $elementPatch['style']['strokeColor'], 'Valid typed style patch changed.');

foreach (array(
    array(),
    array('element_type' => 'bubble'),
    array('style' => array('shape' => 'ellipse')),
    array('element_type' => 'bubble', 'style' => array()),
    array('element_type' => 'bubble', 'style' => array('shape' => 'impact')),
    array('x_unit' => 1_000_001),
    array('unknown' => true),
) as $invalidElementPatch) {
    molManagementExpectError(
        static fn (): array => RequestValidator::elementPatch($invalidElementPatch),
        'mol_invalid_params'
    );
}
molManagementExpectError(
    static fn (): array => RequestValidator::elementCreate(array(
        'page_id' => 7,
        'element_type' => 'bubble',
        'x_unit' => 900_000,
        'y_unit' => 0,
        'w_unit' => 200_000,
        'h_unit' => 100_000,
        'content' => 'invalid geometry',
    )),
    'mol_invalid_params'
);

echo "Manga Overlay content-management unit tests passed.\n";
