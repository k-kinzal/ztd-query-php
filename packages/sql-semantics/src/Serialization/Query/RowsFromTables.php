<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableFunction\RowsFrom;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes a function table as ROWS FROM with each invocation's column definition list.
 * @visibility SqlSemantics
 */
final class RowsFromTables
{
    /**
     * Writes ROWS FROM (invocations) and WITH ORDINALITY when requested.
     */
    public static function write(RowsFrom\RowsFromTable $table): Tree
    {
        return new Tree('rows-from', [
            Build::keyword('ROWS FROM'),
            Build::parentheses(Build::separated(array_map(self::function(...), $table->functions))),
            ...($table->ordinality ? [Build::keyword('WITH ORDINALITY')] : []),
        ]);
    }

    /**
     * Writes one invocation and its column definition list.
     */
    public static function function(RowsFrom\RowsFromFunction $function): Tree
    {
        return new Tree('rows-from-function', [
            Expressions::write($function->call),
            ...($function->columns === [] ? [] : [Build::keyword('AS'), Build::parentheses(Build::separated(array_map(static fn (RowsFrom\DefinedColumn $column): Tree => new Tree('defined-column', [Build::identifier([$column->name], Dialect::PostgreSql), TypeDeclaration::write($column->type)]), $function->columns)))]),
        ]);
    }
}
