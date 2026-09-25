<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Prepared;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Prepared\CreateTableFromExecuteStatement;

/**
 * Binds PostgreSQL CREATE TABLE ... AS EXECUTE: the table header like CREATE TABLE AS, then the prepared query.
 * @visibility SqlSemantics
 */
final class TableFromExecute
{
    /**
     * Binds the statement, or returns null when the EXECUTE does not create a table.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Origin $origin, Node $node, string $prepared, QueryContext $context): ?CreateTableFromExecuteStatement
    {
        $target = Tree::child($node, ['create_as_target']);
        if ($target === null) {
            return null;
        }
        $tables = $context->tables;
        $scope = new Scope($tables->identifiers, queries: $context);
        $parsed = (new \SqlSemantics\Ast\SchemaReader($tables->identifiers, $tables->defaultSchema, $tables->diagnostics->report(...), $tables->schema->grammarVersion))->table($node);
        $aliases = Tree::child($target, ['opt_column_list']);
        $columns = $aliases === null ? [] : array_map(static fn (Node $column): string => $tables->identifiers->parts($column)[0] ?? '', Tree::outer($aliases, ['columnElem']));
        $parameters = Tree::child($node, ['execute_param_clause']);
        $arguments = $parameters === null ? [] : array_map(static fn (Node $argument): Expression => (new ExpressionBinder())->bind($argument, $scope), Tree::outer($parameters, ['a_expr']));
        $data = Tree::child($node, ['opt_with_data']);
        return new CreateTableFromExecuteStatement(
            $origin,
            \SqlSemantics\Binding\Statement\ObjectBinder::name($node, $context),
            $prepared,
            $arguments,
            $columns,
            \SqlSemantics\Binding\Schema\TablePropertiesBinder::bind($parsed, $scope),
            $data === null || !str_contains(strtoupper(Tree::text($data)), 'NO DATA'),
            in_array('EXISTS', Tree::keywords(new Node('header', 0, array_values(array_filter($node->children, static fn ($child): bool => !$child instanceof Node || $child->name === 'OptTemp')))), true),
        );
    }
}
