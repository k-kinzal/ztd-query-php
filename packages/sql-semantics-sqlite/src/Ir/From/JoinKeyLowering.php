<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison as NameComparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;

/**
 * Lowers one merged join into comparisons of already bound input occurrences.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class JoinKeyLowering
{
    /**
     * Resolves the named keys before the enclosing SELECT exports the input slots.
     */
    public function predicate(JoinChain $chain, JoinStep $step, SourceBinding $binding, BoundStatement $operation): Comparison|Logical|null
    {
        Check::input(count($chain->steps) === 1 && !$step->operator->right(), 'A merged join needs two ordinary inputs without RIGHT or FULL.');
        $left = $this->columns($operation, $chain->first);
        $right = $this->columns($operation, $step->relation);
        $keys = $step->constraint instanceof JoinUsing
            ? array_map(static fn (Name $name): string => NameComparison::AsciiInsensitive->fold($name->value), $step->constraint->columns)
            : array_keys(array_intersect_key($right, $left));
        $predicate = null;
        foreach ($keys as $key) {
            Check::input(isset($left[$key], $right[$key]), 'A USING key must identify one declared column on each side.');
            $equality = new Comparison(
                ComparisonOperator::Equal,
                new ColumnReference($binding->slot(0, $left[$key]), 0),
                new ColumnReference($binding->slot(1, $right[$key]), 1),
            );
            $predicate = $predicate === null ? $equality : new Logical(LogicalOperator::And, $predicate, $equality);
        }

        return $predicate;
    }

    /**
     * Maps the unique declared names of an ordinary external source in heading order.
     * @return array<array-key, Column>
     */
    private function columns(BoundStatement $operation, Relation $source): array
    {
        $resolution = $operation->facts->relation($source)->table;
        Check::input($resolution instanceof DeclaredTable && $resolution->table->complete && $resolution->table->kind === RelationKind::BaseTable, 'A merged join requires complete base-table headings.');
        $columns = [];
        foreach ($resolution->table->columns as $column) {
            $key = NameComparison::AsciiInsensitive->fold($column->name->value);
            Check::input(!isset($columns[$key]), 'A merged join requires distinct names in each heading.');
            $columns[$key] = $column;
        }

        return $columns;
    }
}
