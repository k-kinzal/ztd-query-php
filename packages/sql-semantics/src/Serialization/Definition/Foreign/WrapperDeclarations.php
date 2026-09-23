<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignDataWrapperStatement;

/**
 * Serializes wrapper creation and alteration from their distinct operand domains.
 * @visibility SqlSemantics
 */
final class WrapperDeclarations
{
    /**
     * Does not consult original syntax or execute support functions.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof CreateForeignDataWrapperStatement && !$statement instanceof AlterForeignDataWrapperStatement) {
            return null;
        }
        $options = $statement instanceof CreateForeignDataWrapperStatement
            ? array_map(static fn (ForeignOption $option): Tree => WrapperOptions::option($option), $statement->options)
            : array_map(static fn (AddForeignOption|SetForeignOption|DropForeignOption $option): Tree => WrapperOptions::change($option), $statement->options);
        return new Tree('foreign-data-wrapper', [
            Build::keyword($statement instanceof CreateForeignDataWrapperStatement ? 'CREATE FOREIGN DATA WRAPPER' : 'ALTER FOREIGN DATA WRAPPER'), Build::identifier([$statement->name], Dialect::PostgreSql),
            ...WrapperOptions::support($statement->handler, true), ...WrapperOptions::support($statement->validator, false),
            ...($options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated($options))]),
        ]);
    }
}
