<?php

declare(strict_types=1);

use MOL\Content\RewriteManager;
use MOL\Content\WorkContent;
use MOL\Content\WorkMeta;

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $value): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)) ?? '';
    }
}

$molContentCanManage = false;
if (! function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        global $molContentCanManage;
        return 'mol_manage_content' === $capability && $molContentCanManage;
    }
}

function molContentAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (! is_readable($autoload)) {
    throw new RuntimeException('Run Composer before the content unit test.');
}
require_once $autoload;

molContentAssert('mol_work' === WorkContent::POST_TYPE, 'The work post type changed.');
molContentAssert(
    array('mol_genre', 'mol_work_type', 'mol_source_language', 'mol_work_status') === WorkContent::taxonomyNames(),
    'The work taxonomy contract changed.'
);
molContentAssert(
    array('manga', 'manhwa', 'manhua', 'comic', 'webtoon', 'other') === WorkContent::canonicalWorkTypeSlugs(),
    'The canonical work-type dictionary changed.'
);

$titles = WorkMeta::sanitizeAltTitles(array('  Alias  ', '<b>Second</b>', '', 'Alias', array('invalid')));
molContentAssert(array('Alias', 'Second') === $titles, 'Alternative titles were not sanitized and deduplicated.');
molContentAssert(array() === WorkMeta::sanitizeAltTitles('Alias'), 'Non-array alternative titles were accepted.');
molContentAssert('paged' === WorkMeta::sanitizeReaderMode('PAGED'), 'Reader mode was not normalized.');
molContentAssert(
    WorkMeta::DEFAULT_READER_MODE_VALUE === WorkMeta::sanitizeReaderMode('scroll'),
    'Invalid reader mode did not fall back safely.'
);
molContentAssert('ltr' === WorkMeta::sanitizeReadingDirection('LTR'), 'Reading direction was not normalized.');
molContentAssert(
    WorkMeta::DEFAULT_READING_DIRECTION_VALUE === WorkMeta::sanitizeReadingDirection('auto'),
    'Invalid reading direction did not fall back safely.'
);

molContentAssert(! WorkMeta::authorizeMutation(), 'A user without mol_manage_content passed meta authorization.');
$molContentCanManage = true;
molContentAssert(WorkMeta::authorizeMutation(), 'A content manager failed meta authorization.');

$rewrites = new RewriteManager();
$queryVariables = $rewrites->registerQueryVars(array('page', 'mol_chapter'));
molContentAssert(1 === count(array_keys($queryVariables, 'mol_chapter', true)), 'Chapter query var was duplicated.');
molContentAssert(in_array('mol_editor', $queryVariables, true), 'Editor query var was not registered.');

echo "Manga Overlay content unit tests passed.\n";
