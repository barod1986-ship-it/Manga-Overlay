<?php

declare(strict_types=1);

namespace MOL\Database;

use InvalidArgumentException;

final class TableNames
{
    public readonly string $chapters;
    public readonly string $pages;
    public readonly string $elements;
    public readonly string $elementLocks;
    public readonly string $contributions;
    public readonly string $reports;
    public readonly string $readingProgress;
    public readonly string $stylePresets;
    public readonly string $idempotencyKeys;

    public function __construct(string $prefix)
    {
        if (1 !== preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
            throw new InvalidArgumentException('The WordPress table prefix contains unsupported characters.');
        }

        $this->chapters = $prefix . 'mol_chapters';
        $this->pages = $prefix . 'mol_pages';
        $this->elements = $prefix . 'mol_elements';
        $this->elementLocks = $prefix . 'mol_element_locks';
        $this->contributions = $prefix . 'mol_contributions';
        $this->reports = $prefix . 'mol_reports';
        $this->readingProgress = $prefix . 'mol_reading_progress';
        $this->stylePresets = $prefix . 'mol_style_presets';
        $this->idempotencyKeys = $prefix . 'mol_idempotency_keys';
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return array(
            'mol_chapters' => $this->chapters,
            'mol_pages' => $this->pages,
            'mol_elements' => $this->elements,
            'mol_element_locks' => $this->elementLocks,
            'mol_contributions' => $this->contributions,
            'mol_reports' => $this->reports,
            'mol_reading_progress' => $this->readingProgress,
            'mol_style_presets' => $this->stylePresets,
            'mol_idempotency_keys' => $this->idempotencyKeys,
        );
    }
}
