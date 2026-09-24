<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared identifier rules for catalog object addresses.
 * @visibility SqlSemantics
 */
final class CatalogInvariant
{
    /**
     * Requires nonempty components within the object class's qualification depth.
     * @throws InvalidStructure
     */
    public static function name(QualifiedName $name, int $depth): void
    {
        if (count($name->parts) > $depth) {
            throw new InvalidStructure('The object name has more components than its object class allows.');
        }
        foreach ($name->parts as $part) {
            self::identifier($part);
        }
    }

    /**
     * Requires a nonempty identifier.
     * @throws InvalidStructure
     */
    public static function identifier(string $name): void
    {
        if ($name === '') {
            throw new InvalidStructure('A catalog object identifier cannot be empty.');
        }
    }
}
