<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Relation\ProposedRow;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Write\Insertion;
use SqlSemantics\Model\Write\Policy\RowAlias;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Binds MySQL's `AS alias [(columns)]` after VALUES or SET to the proposed row it names, one column per inserted column.
 * @visibility SqlSemantics
 */
final class RowAliasBinder
{
    /**
     * Returns the named proposed row, or null when the statement is no insertion or does not name it.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $statement, ?Insertion $insertion, QueryContext $context, string $scopeId): ?RowAlias
    {
        $node = QueryNodes::local($statement, ['opt_values_reference'])[0] ?? null;
        $nameNode = $node === null ? null : Tree::child($node, ['ident']);
        if ($insertion === null || $node === null || $nameNode === null) {
            return null;
        }
        $identifiers = $context->tables->identifiers;
        $name = $identifiers->parts($nameNode)[0];
        $list = Tree::child($node, ['opt_derived_column_list']);
        $aliases = $list === null ? [] : array_map(static fn (Node $alias): string => $identifiers->parts($alias)[0], Tree::outer($list, ['ident']));
        $target = $insertion->target;
        $columns = self::columns($insertion, $node);
        if ($columns === [] && !$target->declaration->resolved) {
            $columns = array_map(static fn (string $alias): ColumnDefinition => new ColumnDefinition($alias, \SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::MySql, 'unknown'), \SqlSemantics\Type\Nullability::Unknown, $node), $aliases);
        }
        if ($identifiers->relationEqual($name, $target->alias ?? $target->declaration->name) || ($aliases !== [] && count($aliases) !== count($columns))) {
            throw new InvalidSql(InputViolation::InsertRowAlias, $node);
        }
        $columns = $aliases === [] ? $columns : array_map(static fn (ColumnDefinition $column, string $alias): ColumnDefinition => $column->withName($alias), $columns, $aliases);
        if (count(array_unique(array_map(static fn (ColumnDefinition $column): string => strtolower($column->name), $columns))) !== count($columns)) {
            throw new InvalidSql(InputViolation::InsertRowAlias, $node);
        }
        $declaration = new TableDefinition('', $name, $columns, [], $node, $target->declaration->resolved);
        return new RowAlias(new ProposedRow($context->ids->relation(), $scopeId, $declaration, $name, $node, $target), $aliases);
    }

    /**
     * Declares one proposed column per inserted column; an unresolved destination keeps its written name with an unknown type.
     * @return list<ColumnDefinition>
     */
    public static function columns(Insertion $insertion, Node $source): array
    {
        $dialect = $insertion->target->declaration->columns[0]->type->dialect ?? \SqlSemantics\Dialect::MySql;
        return array_map(static function (\SqlSemantics\Model\Write\Storage\Path $path) use ($source, $dialect): ColumnDefinition {
            $column = $path->column();
            $parts = $column->referenceParts();
            $symbol = $column->columnBinding()?->column;
            return $symbol === null
                ? new ColumnDefinition($parts[count($parts) - 1] ?? '?column?', \SqlSemantics\Type\TypeDescriptor::builtin($dialect, 'unknown'), \SqlSemantics\Type\Nullability::Unknown, $source)
                : new ColumnDefinition($symbol->name, $symbol->type, $symbol->nullability, $source);
        }, $insertion->columns);
    }

    /**
     * Adds the proposed row beside the destination, so a name both expose is ambiguous as in MySQL.
     */
    public static function scope(Scope $scope, ?RowAlias $alias): Scope
    {
        return $alias === null ? $scope : new Scope($scope->identifiers, [...$scope->relations, $alias->row], $scope->extensions, $scope->parent, $scope->queries, $scope->merged, $scope->outputs, $scope->detached);
    }

    /**
     * Narrows ON DUPLICATE KEY UPDATE destinations to the target beside a row alias, since only the target's columns can be assigned; null keeps the value scope.
     */
    public static function destinations(Scope $scope, ?RowAlias $alias): ?Scope
    {
        return $alias === null ? null : new Scope($scope->identifiers, array_values(array_filter($scope->relations, static fn (\SqlSemantics\Model\TableUse $relation): bool => $relation !== $alias->row)), $scope->extensions, $scope->parent, $scope->queries, $scope->merged, $scope->outputs, $scope->detached);
    }
}
