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
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison as NameComparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;

/**
 * Lowers a merged join into comparisons against specified occurrences in its left prefix.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class JoinKeyLowering
{
    /**
     * Resolves the named keys before the enclosing SELECT exports the input slots.
     */
    public function predicate(JoinChain $chain, JoinStep $step, SourceBinding $binding, BoundStatement $operation, int $position): Comparison|Logical|null
    {
        Check::input(
            count($chain->steps) === 1 || array_filter($chain->steps, static fn (JoinStep $item): bool => $item->operator->right()) === [],
            'A merged prefix requires INNER, CROSS, or LEFT steps.',
        );
        $declarations = new SourceDeclarations();
        $left = $declarations->prefixHeading($operation, $chain, $position);
        $right = $declarations->mergeHeading($operation, $step->relation);
        $keys = $step->constraint instanceof JoinUsing
            ? array_map(static fn (Name $name): string => NameComparison::AsciiInsensitive->fold($name->value), $step->constraint->columns)
            : array_keys(array_intersect_key($right, $left));
        $predicate = null;
        foreach ($keys as $key) {
            Check::input(isset($left[$key], $right[$key]), 'A USING key must identify one declared column on each side.');
            [$occurrence, $column] = $left[$key];
            $rightOccurrence = $position + 1;
            $equality = new Comparison(
                ComparisonOperator::Equal,
                new ColumnReference($binding->slot($occurrence, $column), $occurrence),
                new ColumnReference($binding->slot($rightOccurrence, $right[$key]), $rightOccurrence),
            );
            $predicate = $predicate === null ? $equality : new Logical(LogicalOperator::And, $predicate, $equality);
        }

        return $predicate;
    }
}
