<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal as Statement;
use SqlSemantics\Serialization\Definition\SchemaCommands;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes the DROP forms of sequences, foreign tables, schema objects, named objects, members, and types.
 * @visibility SqlSemantics
 */
final class CatalogRemovals
{
    /**
     * Returns null for statements outside the catalog removal family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\DropRelationsStatement => SchemaCommands::drop($statement->relationKind->value, $statement->names, $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\DropSchemaObjectsStatement => SchemaCommands::drop($statement->objectKind->value, $statement->names, $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\DropNamedObjectsStatement => SchemaCommands::drop($statement->objectKind->value, array_map(static fn (string $name): QualifiedName => new QualifiedName([$name]), $statement->names), $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\DropRelationMemberStatement => new Tree('drop-member', [Build::keyword('DROP ' . $statement->memberKind->value . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier([$statement->name], $dialect), Build::keyword('ON'), Build::identifier($statement->table->parts, $dialect), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\DropTypesStatement => new Tree('drop-types', [Build::keyword('DROP ' . $statement->typeKind->value . ($statement->ifExists ? ' IF EXISTS' : '')), Build::separated(array_map(TypeDeclaration::write(...), $statement->types)), Build::keyword($statement->behavior->value)]),
            default => null,
        };
    }
}
