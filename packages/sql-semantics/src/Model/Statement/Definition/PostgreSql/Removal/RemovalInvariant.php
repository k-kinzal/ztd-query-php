<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Removal;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared operand rules of PostgreSQL DROP forms with target lists.
 * @visibility SqlSemantics
 */
final class RemovalInvariant
{
    /**
     * Requires PostgreSQL and a nonempty list of well-formed qualified names.
     * @param non-empty-list<QualifiedName> $names
     * @throws InvalidStructure
     */
    public static function names(Origin $origin, array $names, int $depth): void
    {
        self::dialect($origin);
        Collections::objects(Collections::nonEmpty($names), QualifiedName::class);
        foreach ($names as $name) {
            CatalogInvariant::name($name, $depth);
        }
    }

    /**
     * Requires PostgreSQL and a nonempty list of unqualified identifiers.
     * @param non-empty-list<string> $names
     * @throws InvalidStructure
     */
    public static function identifiers(Origin $origin, array $names): void
    {
        self::dialect($origin);
        Collections::strings(Collections::nonEmpty($names));
        foreach ($names as $name) {
            CatalogInvariant::identifier($name);
        }
    }

    /**
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This removal form requires PostgreSQL.');
        }
    }
}
