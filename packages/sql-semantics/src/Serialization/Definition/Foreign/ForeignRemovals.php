<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignDataWrappersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignServersStatement;

/**
 * Serializes complete removal target lists and dependent-object policies.
 * @visibility SqlSemantics
 */
final class ForeignRemovals
{
    /**
     * Quotes each name as a single local identifier.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof DropForeignServersStatement && !$statement instanceof DropForeignDataWrappersStatement) {
            return null;
        }
        return new Tree('foreign-removal', [
            Build::keyword($statement instanceof DropForeignServersStatement ? 'DROP SERVER' : 'DROP FOREIGN DATA WRAPPER'),
            ...($statement->ifExists ? [Build::keyword('IF EXISTS')] : []),
            Build::separated(array_map(static fn (string $name) => Build::identifier([$name], Dialect::PostgreSql), $statement->names)),
            ...($statement->behavior->value === '' ? [] : [Build::keyword($statement->behavior->value)]),
        ]);
    }
}
