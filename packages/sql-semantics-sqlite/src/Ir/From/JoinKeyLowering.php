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
        $declarations = new SourceDeclarations();
        $left = $declarations->mergeHeading($operation, $chain->first);
        $right = $declarations->mergeHeading($operation, $step->relation);
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
}
