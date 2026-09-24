<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Operand rules shared by PostgreSQL type system definitions: the dialect, schema-qualified names, and declared types.
 * @visibility SqlSemantics
 */
final class TypeSystemInvariant
{
    /**
     * Type system DDL is a PostgreSQL operation.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Type system definitions require PostgreSQL.');
        }
    }

    /**
     * A schema-scoped object is named by at most a schema and its own name.
     * @throws InvalidStructure
     */
    public static function name(QualifiedName $name): void
    {
        CatalogInvariant::name($name, 2);
    }

    /**
     * An unqualified name is a nonempty identifier.
     * @throws InvalidStructure
     */
    public static function identifier(string $name): void
    {
        CatalogInvariant::identifier($name);
    }

    /**
     * Declared types are PostgreSQL type declarations.
     * @throws InvalidStructure
     */
    public static function type(TypeDescriptor $type): void
    {
        if ($type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Type system definitions require PostgreSQL type declarations.');
        }
    }
}
