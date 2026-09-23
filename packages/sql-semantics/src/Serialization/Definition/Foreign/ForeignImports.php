<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AllForeignTables;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignRelation;
use SqlSemantics\Model\Definition\Foreign\ImportOnlyTables;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes import endpoints and the closed set of remote selection forms.
 * @visibility SqlSemantics
 */
final class ForeignImports
{
    /**
     * Returns null for operations outside the foreign schema import family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof ImportForeignSchemaStatement) {
            return null;
        }
        $selection = $statement->selection;
        $filter = $selection instanceof AllForeignTables ? [] : [Build::keyword($selection instanceof ImportOnlyTables ? 'LIMIT TO' : 'EXCEPT'), Build::parentheses(Build::separated(array_map(static fn (ForeignRelation $relation): Tree => new Tree('remote-relation', [...($relation->includeDescendants ? [] : [Build::keyword('ONLY')]), Build::identifier($relation->name->parts, Dialect::PostgreSql)]), $selection->tables)))];
        $options = $statement->options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated(array_map(static fn (ForeignOption $option): Tree => new Tree('foreign-option', [Build::identifier([$option->name], Dialect::PostgreSql), Expressions::write($option->value)]), $statement->options)))];
        return new Tree('import-foreign-schema', [
            Build::keyword('IMPORT FOREIGN SCHEMA'), Build::identifier([$statement->remoteSchema], Dialect::PostgreSql), ...$filter,
            Build::keyword('FROM SERVER'), Build::identifier([$statement->server], Dialect::PostgreSql),
            Build::keyword('INTO'), Build::identifier([$statement->localSchema], Dialect::PostgreSql), ...$options,
        ]);
    }
}
