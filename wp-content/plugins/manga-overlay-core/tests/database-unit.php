<?php

declare(strict_types=1);

use MOL\Database\JsonDocument;
use MOL\Database\Schema;
use MOL\Database\TableNames;
use MOL\Domain\Validation\AllowedValues;
use MOL\Domain\Validation\GeometryValidator;
use MOL\Domain\Validation\PresetScopeValidator;
use MOL\Domain\Validation\StyleValidator;
use MOL\Domain\Validation\ValidationException;

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
    {
        return json_encode($value, $flags, $depth);
    }
}

function molDatabaseAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function molDatabaseRejects(callable $operation, string $expectedField): void
{
    try {
        $operation();
    } catch (ValidationException $error) {
        molDatabaseAssert($expectedField === $error->field(), sprintf(
            'Expected validation field %s; received %s.',
            $expectedField,
            $error->field()
        ));
        return;
    }

    throw new RuntimeException(sprintf('Expected validation failure for %s.', $expectedField));
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    throw new RuntimeException('Run Composer before the database unit test.');
}
require_once $autoload;

$tables = new TableNames('wp_7_');
molDatabaseAssert(9 === count($tables->all()), 'The schema must contain exactly nine domain tables.');
molDatabaseAssert('wp_7_mol_elements' === $tables->elements, 'Multisite table prefix was not preserved.');

$statements = Schema::statements('wp_', 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
molDatabaseAssert(9 === count($statements), 'Schema did not return nine CREATE TABLE statements.');
molDatabaseAssert(9 === count(Schema::requiredColumns()), 'Schema column contract must cover nine tables.');
molDatabaseAssert(9 === count(Schema::requiredIndexes()), 'Schema index contract must cover nine tables.');
$schemaSql = implode("\n", $statements);
foreach (array_keys($tables->all()) as $suffix) {
    molDatabaseAssert(str_contains($schemaSql, 'wp_' . $suffix), sprintf('Schema is missing %s.', $suffix));
}
molDatabaseAssert(9 === substr_count($schemaSql, 'ENGINE=InnoDB'), 'Every table must explicitly use InnoDB.');
molDatabaseAssert(! str_contains(strtoupper($schemaSql), 'FOREIGN KEY'), 'T-04 must not create foreign keys.');
molDatabaseAssert(! str_contains(strtoupper($schemaSql), ' ENUM('), 'T-04 must validate dictionaries in PHP.');
molDatabaseAssert(
    str_contains($schemaSql, 'sort_order decimal(14,4) NOT NULL DEFAULT 0'),
    'Chapter sort_order lost its portable decimal definition.'
);

AllowedValues::chapterTranslationStatus('needs_review');
AllowedValues::readerMode(null, true);
AllowedValues::direction('rtl');
AllowedValues::elementType('sfx');
AllowedValues::reportType('placement');
AllowedValues::reportStatus('in_review');
AllowedValues::presetScope('work');
molDatabaseRejects(static fn () => AllowedValues::elementType('video'), 'element_type');
molDatabaseRejects(static fn () => AllowedValues::readerMode('scroll'), 'reader_mode');
molDatabaseRejects(
    static fn () => AllowedValues::readerMode('scroll', true, 'reader_mode_override'),
    'reader_mode_override'
);

$geometry = array(
    'x_unit' => 100000,
    'y_unit' => 200000,
    'w_unit' => 300000,
    'h_unit' => 400000,
    'rotation_mdeg' => -12000,
    'z_index' => 4,
);
GeometryValidator::validate($geometry);
molDatabaseRejects(
    static fn () => GeometryValidator::validate(array_merge($geometry, array('w_unit' => 950000))),
    'w_unit'
);
$clamped = GeometryValidator::clamp(999999, -50, 500, 0, 500000, -5000);
GeometryValidator::validate($clamped);
molDatabaseAssert(1 === $clamped['w_unit'], 'Geometry clamp did not preserve a valid width at the edge.');
molDatabaseAssert(0 === $clamped['y_unit'], 'Geometry clamp did not constrain y_unit.');
molDatabaseAssert(360000 === $clamped['rotation_mdeg'], 'Geometry clamp did not constrain rotation.');

$validStyles = array(
    'bubble' => array('shape' => 'ellipse', 'tail' => array(), 'color' => '#112233'),
    'narration' => array('shape' => 'rounded_rect', 'backgroundOpacity' => 0.8),
    'free_text' => array('shape' => 'none', 'autoFit' => true),
    'sfx' => array(
        'shape' => 'impact',
        'burst' => array('points' => 16, 'depth' => 0.7),
        'scaleX' => 1.2,
        'scaleY' => 0.8,
    ),
);
foreach ($validStyles as $elementType => $style) {
    StyleValidator::validate($elementType, $style);
}
StyleValidator::validate('free_text', array());
molDatabaseRejects(static fn () => StyleValidator::validate('bubble', array('shape' => 'impact')), 'shape');
molDatabaseRejects(static fn () => StyleValidator::validate('narration', array('tail' => null)), 'tail');
molDatabaseRejects(static fn () => StyleValidator::validate('sfx', array('color' => 'red')), 'color');
molDatabaseRejects(static fn () => StyleValidator::validate('sfx', array('unknown' => true)), 'unknown');
molDatabaseRejects(
    static fn () => StyleValidator::validate('sfx', array('burst' => array('points' => 10))),
    'burst.points'
);

PresetScopeValidator::validate('personal', 5, null);
PresetScopeValidator::validate('work', null, 9);
PresetScopeValidator::validate('global', null, null);
molDatabaseRejects(static fn () => PresetScopeValidator::validate('global', 5, null), 'scope');

$encodedObject = JsonDocument::encodeObject(array());
molDatabaseAssert('{}' === $encodedObject, 'An empty style must be encoded as a JSON object.');
$styleDocument = array('shape' => 'impact', 'burst' => array('points' => 16));
molDatabaseAssert($styleDocument === JsonDocument::decodeObject(JsonDocument::encodeObject($styleDocument)), 'Style JSON did not round-trip.');

echo "Manga Overlay database unit tests passed.\n";
