<?php

declare(strict_types=1);

namespace MOL\REST;

use MOL\Domain\ElementStyles;

final class ElementPresenter
{
    /** @param array<string, mixed> $element @return array<string, mixed> */
    public static function one(array $element): array
    {
        return array(
            'id' => (int) $element['id'],
            'page_id' => (int) $element['page_id'],
            'target_lang' => (string) $element['target_lang'],
            'element_type' => (string) $element['element_type'],
            'x_unit' => (int) $element['x_unit'],
            'y_unit' => (int) $element['y_unit'],
            'w_unit' => (int) $element['w_unit'],
            'h_unit' => (int) $element['h_unit'],
            'rotation_mdeg' => (int) $element['rotation_mdeg'],
            'z_index' => (int) $element['z_index'],
            'content' => (string) $element['content'],
            'style' => ElementStyles::resolve(
                (string) $element['element_type'],
                is_array($element['style']) ? $element['style'] : array()
            ),
            'version' => (int) $element['version'],
            'created_by' => (int) $element['created_by'],
            'updated_by' => (int) $element['updated_by'],
            'created_at' => PresenterSupport::dateTime($element['created_at']) ?? '',
            'updated_at' => PresenterSupport::dateTime($element['updated_at']) ?? '',
        );
    }

    /** @param list<array<string, mixed>> $elements @return list<array<string, mixed>> */
    public static function many(array $elements): array
    {
        return array_map(self::one(...), $elements);
    }
}
