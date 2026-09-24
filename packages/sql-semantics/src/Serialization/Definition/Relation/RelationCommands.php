<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Catalog\Kind\RelationKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation as Statement;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;

/**
 * Writes commands that address one relation with its existence and descendant policies.
 * @visibility SqlSemantics
 */
final class RelationCommands
{
    /**
     * Returns null for statements outside the relation command family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\RenameRelationStatement => new Tree('rename-relation', [self::target($statement->relationKind, $statement->name, $statement->ifExists, $statement->only), Build::keyword('RENAME TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\RenameRelationColumnStatement => new Tree('rename-relation-column', [self::target($statement->relationKind, $statement->name, $statement->ifExists, $statement->only), Build::keyword('RENAME COLUMN'), Build::identifier([$statement->column], $dialect), Build::keyword('TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\RenameTableConstraintStatement => new Tree('rename-table-constraint', [self::target(RelationKind::Table, $statement->table, $statement->ifExists, $statement->only), Build::keyword('RENAME CONSTRAINT'), Build::identifier([$statement->constraint], $dialect), Build::keyword('TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\AlterRelationStatement => new Tree('alter-relation', [self::target($statement->relationKind, $statement->name, $statement->ifExists, $statement->only), Build::separated(array_map(RelationActions::write(...), $statement->actions))]),
            $statement instanceof Statement\MoveTablespaceRelationsStatement => new Tree('move-relations', [Build::keyword('ALTER ' . $statement->relationKind->value . ' ALL IN TABLESPACE'), Build::identifier([$statement->tablespace], $dialect), ...($statement->owners === [] ? [] : [Build::keyword('OWNED BY'), Build::separated(array_map(OwnershipCommands::role(...), $statement->owners))]), Build::keyword('SET TABLESPACE'), Build::identifier([$statement->newTablespace], $dialect), ...($statement->nowait ? [Build::keyword('NOWAIT')] : [])]),
            $statement instanceof Statement\SetRelationSchemaStatement => new Tree('set-relation-schema', [self::target($statement->relationKind, $statement->name, $statement->ifExists, $statement->only), Build::keyword('SET SCHEMA'), Build::identifier([$statement->schema], $dialect)]),
            default => null,
        };
    }

    /**
     * Writes the ALTER head with the relation class, existence policy, descendant policy, and name.
     */
    public static function target(RelationKind $kind, QualifiedName $name, bool $ifExists, bool $only): Tree
    {
        return new Tree('relation-target', [Build::keyword('ALTER ' . $kind->value . ($ifExists ? ' IF EXISTS' : '') . ($only ? ' ONLY' : '')), Build::identifier($name->parts, Dialect::PostgreSql)]);
    }
}
