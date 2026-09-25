<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Recursion;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\Model\Query\Recursion\CycleClause;
use SqlSemantics\Model\Query\Recursion\SearchClause;
use SqlSemantics\Model\Query\Recursion\SearchOrder;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Type\Identity\ArrayDimension;
use SqlSemantics\Type\Identity\ArrayStorage;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds the PostgreSQL SEARCH and CYCLE clauses written after a common table expression, and the columns they add.
 * @visibility SqlSemantics
 */
final class RecursionClauses
{
    /**
     * Binds `SEARCH {BREADTH | DEPTH} FIRST BY columns SET column`, or returns null when the definition has none.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function search(Node $cte, QueryContext $context): ?SearchClause
    {
        $clause = Tree::child($cte, ['opt_search_clause']);
        if ($clause === null) {
            return null;
        }
        $names = self::names($clause, $context);
        return new SearchClause(in_array('BREADTH', Tree::keywords($clause), true) ? SearchOrder::BreadthFirst : SearchOrder::DepthFirst, self::listed($clause, $context), $names[0] ?? '');
    }

    /**
     * Binds `CYCLE columns SET mark [TO value DEFAULT value] USING path`, or returns null when the definition has none.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function cycle(Node $cte, QueryContext $context): ?CycleClause
    {
        $clause = Tree::child($cte, ['opt_cycle_clause']);
        if ($clause === null) {
            return null;
        }
        $names = self::names($clause, $context);
        $scope = new Scope($context->tables->identifiers, queries: $context);
        $values = array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), array_values(array_filter($clause->children, static fn ($child): bool => $child instanceof Node && $child->name === 'AexprConst')));
        return new CycleClause(self::listed($clause, $context), $names[0] ?? '', $values[0] ?? null, $values[1] ?? null, $names[1] ?? '');
    }

    /**
     * Reads the columns listed after BY or CYCLE.
     *
     * @return list<string>
     */
    public static function listed(Node $clause, QueryContext $context): array
    {
        $list = Tree::child($clause, ['columnList']);
        return $list === null ? [] : array_map(static fn (Node $column): string => $context->tables->identifiers->parts($column)[0] ?? '', Tree::outer($list, ['columnElem']));
    }

    /**
     * Reads the column names the clause adds, in written order.
     *
     * @return list<string>
     */
    public static function names(Node $clause, QueryContext $context): array
    {
        return array_map(static fn (Node $name): string => $context->tables->identifiers->parts($name)[0] ?? '', array_values(array_filter($clause->children, static fn ($child): bool => $child instanceof Node && $child->name === 'ColId')));
    }

    /**
     * Returns the relation a reference to the definition sees: the query's columns, then the columns SEARCH and
     * CYCLE add.
     */
    public static function declaration(CommonTableExpression $definition, Node $source): \SqlSemantics\Schema\TableDefinition
    {
        $declaration = \SqlSemantics\Binding\Query\QueryRelation::declaration($definition->query, $definition->name, $definition->columns, $source);
        $added = self::columns($definition, $source);
        return $added === [] ? $declaration : new \SqlSemantics\Schema\TableDefinition($declaration->schema, $declaration->name, [...$declaration->columns, ...$added], $declaration->constraints, $source, $declaration->resolved);
    }

    /**
     * Returns the columns a reference to the definition sees after the query's own columns: the search sequence,
     * the cycle mark and the cycle path.
     *
     * @return list<ColumnDefinition>
     */
    public static function columns(CommonTableExpression $definition, Node $source): array
    {
        $record = TypeDescriptor::builtin(Dialect::PostgreSql, 'record');
        $records = new TypeDescriptor(Dialect::PostgreSql, new ArrayStorage($record, [new ArrayDimension()]));
        $columns = [];
        if ($definition->search !== null) {
            $columns[] = new ColumnDefinition($definition->search->sequenceColumn, $definition->search->order === SearchOrder::BreadthFirst ? $record : $records, Nullability::NotNull, $source);
        }
        if ($definition->cycle !== null) {
            $columns[] = new ColumnDefinition($definition->cycle->markColumn, $definition->cycle->markValue->type ?? TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::NotNull, $source);
            $columns[] = new ColumnDefinition($definition->cycle->pathColumn, $records, Nullability::NotNull, $source);
        }
        return $columns;
    }
}
