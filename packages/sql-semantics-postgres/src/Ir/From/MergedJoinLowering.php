<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind as ParsedKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinUsing;

/**
 * Binds PostgreSQL merged keys to built-in int4 fields of two ordinary occurrences.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class MergedJoinLowering
{
    /**
     * Resolves the key list before either input's slots are exported.
     */
    public function predicate(Join $join, SourceBinding $left, SourceBinding $right): BooleanLiteral|IntegerComparison|LogicalPredicate|null
    {
        $leftHeading = $left->mergedHeading();
        $rightHeading = $right->mergedHeading();
        $keys = $join->condition instanceof JoinUsing
            ? array_map(static fn (Name $name): string => ':' . $name->value, $join->condition->columns)
            : array_keys(array_intersect_key($leftHeading, $rightHeading));
        Check::input(count($keys) === count(array_unique($keys)), 'A merged join cannot name the same field twice.');
        $predicate = null;
        foreach ($keys as $key) {
            Check::input(isset($leftHeading[$key], $rightHeading[$key]), 'A merged key must be a declared column of both inputs.');
            $equal = new IntegerComparison(
                IntegerOperator::Equal,
                $left->slotFor($leftHeading[$key]),
                new ColumnReference($right->slotFor($rightHeading[$key])->slot, 1),
            );
            $predicate = $predicate === null ? $equal : new LogicalPredicate(LogicalOperator::And, $predicate, $equal);
        }

        return $predicate ?? ($join->kind === ParsedKind::Inner ? null : new BooleanLiteral(true));
    }
}
