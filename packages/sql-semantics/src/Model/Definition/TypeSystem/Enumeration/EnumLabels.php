<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Enumeration;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The rules enum labels follow: at most 63 bytes, and unique within one type.
 * @visibility SqlSemantics
 */
final class EnumLabels
{
    /**
     * PostgreSQL stores a label in a name, which holds 63 bytes.
     * @throws InvalidStructure
     */
    public static function label(string $label): void
    {
        if (strlen($label) > 63) {
            throw new InvalidStructure('An enum label is at most 63 bytes long.');
        }
    }

    /**
     * An enum type lists each label once.
     * @param list<string> $labels
     * @throws InvalidStructure
     */
    public static function labels(array $labels): void
    {
        foreach ($labels as $label) {
            self::label($label);
        }
        if (count(array_unique($labels)) !== count($labels)) {
            throw new InvalidStructure('An enum type lists each label once.');
        }
    }
}
