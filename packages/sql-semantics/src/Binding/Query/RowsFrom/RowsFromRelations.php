<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\RowsFrom;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\BoundRelation;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\QueryRelation;
use SqlSemantics\Binding\Query\RelationFactory;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\TableFunction\RowsFrom;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Derives the result columns and relation occurrence of a function table.
 * @visibility SqlSemantics
 */
final class RowsFromRelations
{
    /**
     * Applies the table alias and its column aliases.
     * @throws InvalidStructure
     */
    public static function relation(Node $source, RowsFrom\RowsFromTable $table, ?Node $alias, QueryContext $context, Scope $scope, ?Scope $parent, string $scopeId): BoundRelation
    {
        $clause = $alias === null ? null : Tree::child($alias, ['alias_clause']);
        $label = $alias === null ? null : Tree::child($alias, ['ColId']);
        $names = $clause !== null ? (new RelationFactory())->aliases($clause, $context) : ($label === null ? [] : [$context->tables->identifiers->name($label->tokens()[0] ?? throw new InvalidStructure('A function table alias requires its name.'))]);
        $name = $names[0] ?? null;
        $aliases = array_slice($names, 1);
        $outputs = self::outputs($source, $table, $aliases);
        $declaration = QueryRelation::columns($outputs, $name ?? strtolower($table->functions[0]->call->spelling() ?? 'rows'), [], $source);
        $relation = new RowsFrom\RowsFromRelation($context->ids->relation(), $scopeId, $declaration, $name, $source, $table, $outputs, $aliases);
        return new BoundRelation($relation, new Scope($scope->identifiers, [$relation], parent: $parent, queries: $context));
    }

    /**
     * Function columns in invocation order, then the ordinality column; with several invocations every function column may be NULL-padded.
     * Column aliases beyond the known columns name further columns of the last invocation without a column definition list, whose result type may be composite.
     * @param list<string> $aliases
     * @return list<OutputColumn>
     * @throws InvalidStructure
     */
    public static function outputs(Node $source, RowsFrom\RowsFromTable $table, array $aliases): array
    {
        $padded = count($table->functions) > 1;
        $specifications = self::columns($table);
        $extra = count($aliases) - array_sum(array_map(count(...), $specifications)) - ($table->ordinality ? 1 : 0);
        $open = array_keys(array_filter($table->functions, static fn (RowsFrom\RowsFromFunction $function): bool => $function->columns === []));
        if ($extra > 0) {
            $last = $open === [] ? throw new InvalidStructure('A function table has fewer columns than column aliases.') : $open[count($open) - 1];
            $call = $table->functions[$last]->call;
            for ($index = 1; $index <= $extra; ++$index) {
                $specifications[$last][] = [strtolower($call->spelling() ?? 'function') . $index, $call->type, $call->nullability];
            }
        }
        $outputs = [];
        foreach ($specifications as $position => $columns) {
            foreach ($columns as [$name, $type, $nullability]) {
                $facts = new ExpressionFacts($type, $padded ? Nullability::MaybeNull : $nullability);
                $outputs[] = new OutputColumn(count($outputs), $aliases[count($outputs)] ?? $name, new RowsFrom\RowsFromColumn($facts, $source, $table, $position, $name));
            }
        }
        if ($table->ordinality) {
            $facts = new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'bigint'), Nullability::NotNull);
            $outputs[] = new OutputColumn(count($outputs), $aliases[count($outputs)] ?? 'ordinality', new RowsFrom\RowsFromColumn($facts, $source, $table, null, 'ordinality'));
        }
        return $outputs;
    }

    /**
     * Returns each invocation's known columns: its column definition list, or one column named after the function.
     * @return array<int, list<array{string, TypeDescriptor, Nullability}>>
     */
    public static function columns(RowsFrom\RowsFromTable $table): array
    {
        $columns = [];
        foreach ($table->functions as $position => $function) {
            $columns[$position] = $function->columns === [] ? [[strtolower($function->call->spelling() ?? 'function'), $function->call->type, $function->call->nullability]] : array_map(static fn (RowsFrom\DefinedColumn $column): array => [$column->name, $column->type, Nullability::MaybeNull], $function->columns);
        }
        return $columns;
    }
}
