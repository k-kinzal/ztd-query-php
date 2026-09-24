<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared operand rules of PostgreSQL commands that address one relation by name.
 * @visibility SqlSemantics
 */
final class RelationInvariant
{
    /**
     * Every relation command is a PostgreSQL operation.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Relation commands require PostgreSQL.');
        }
    }

    /**
     * ONLY excludes descendant tables and therefore applies to tables and foreign tables alone.
     * @throws InvalidStructure
     */
    public static function target(RelationKind $kind, QualifiedName $name, bool $only): void
    {
        CatalogInvariant::name($name, 3);
        if ($only && !in_array($kind, [RelationKind::Table, RelationKind::ForeignTable], true)) {
            throw new InvalidStructure('ONLY applies to tables and foreign tables.');
        }
    }

    /**
     * Restricts a command to the relation classes whose grammar offers it.
     * @param non-empty-list<RelationKind> $kinds
     * @throws InvalidStructure
     */
    public static function kind(RelationKind $kind, array $kinds): void
    {
        if (!in_array($kind, $kinds, true)) {
            throw new InvalidStructure('The relation class does not offer this command.');
        }
    }
}
