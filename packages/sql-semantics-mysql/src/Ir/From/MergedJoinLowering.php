<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;

/**
 * Binds MySQL merged keys to signed integer fields of two ordinary occurrences.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class MergedJoinLowering
{
    /**
     * Resolves the key list before either input's slots are exported.
     */
    public function predicate(JoinedTable $join, SourceBinding $left, SourceBinding $right): BooleanLiteral|IntegerComparison|LogicalPredicate|null
    {
        $leftHeading = $left->mergedHeading();
        $rightHeading = $right->mergedHeading();
        $fields = $join->using === []
            ? array_keys(array_intersect_key($leftHeading, $rightHeading))
            : array_map(static fn (Name $name): string => Comparison::AsciiInsensitive->fold($name->value), $join->using);
        Check::input(count($fields) === count(array_unique($fields)), 'A merged join cannot name the same field twice.');
        $predicate = null;
        foreach ($fields as $key) {
            Check::input(isset($leftHeading[$key], $rightHeading[$key]), 'A merged key must be a declared column of both inputs.');
            $equal = new IntegerComparison(
                IntegerOperator::Equal,
                $left->slotFor($leftHeading[$key]),
                new ColumnReference($right->slotFor($rightHeading[$key])->slot, 1),
            );
            $predicate = $predicate === null ? $equal : new LogicalPredicate(LogicalOperator::And, $predicate, $equal);
        }

        return $predicate ?? ($join->operator->keepsLeft() || $join->operator->keepsRight() ? new BooleanLiteral(true) : null);
    }
}
