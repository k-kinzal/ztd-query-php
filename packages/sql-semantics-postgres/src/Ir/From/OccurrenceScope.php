<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerNull;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Validates signed predicates against the exact table occurrences at their boundary.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class OccurrenceScope
{
    /**
     * Closes each predicate over the owning occurrence layout and bounds its expanded tree.
     * @param list<TableSource|DerivedSource> $inputs
     */
    public function predicate(
        BooleanColumnReference|BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest|null $condition,
        array $inputs,
        string $invalidSlot = 'A predicate slot must belong to a visible input occurrence.',
    ): void {
        if ($condition !== null) {
            $this->checkTree($condition, $inputs, $invalidSlot, 0, 0);
        }
    }

    /**
     * Validates one subtree and returns the number of visited occurrences.
     *
     * @param list<TableSource|DerivedSource> $inputs The visible input occurrences
     */
    public function checkTree(
        BooleanColumnReference|BooleanLiteral|ColumnReference|IntegerComparison|IntegerDistinctness|IntegerMembership|IntegerRange|IntegerLiteral|LogicalPredicate|NullLiteral|NullTest|TextColumnReference|TextComparison|TextLiteral|TruthTest|WideIntegerColumnReference|WideIntegerLiteral|WideIntegerNull $operand,
        array $inputs,
        string $invalidSlot,
        int $depth,
        int $count,
    ): int {
        Check::input($depth <= 32 && ++$count <= 8192, 'Predicate depth or expanded node count exceeds the IR profile.');
        if ($operand instanceof ColumnReference || $operand instanceof TextColumnReference || $operand instanceof BooleanColumnReference || $operand instanceof WideIntegerColumnReference) {
            $this->column($operand, $inputs, $invalidSlot);
        }
        $children = match (true) {
            $operand instanceof LogicalPredicate => $operand->right === null ? [$operand->left] : [$operand->left, $operand->right],
            $operand instanceof IntegerComparison, $operand instanceof IntegerDistinctness, $operand instanceof TextComparison => [$operand->left, $operand->right],
            $operand instanceof IntegerRange => [$operand->subject, $operand->lower, $operand->upper],
            $operand instanceof IntegerMembership => [$operand->subject, ...$operand->alternatives],
            $operand instanceof NullTest, $operand instanceof TruthTest => [$operand->operand],
            $operand instanceof BooleanLiteral, $operand instanceof BooleanColumnReference, $operand instanceof ColumnReference, $operand instanceof IntegerLiteral, $operand instanceof NullLiteral, $operand instanceof TextColumnReference, $operand instanceof TextLiteral, $operand instanceof WideIntegerColumnReference, $operand instanceof WideIntegerLiteral, $operand instanceof WideIntegerNull => [],
        };
        foreach ($children as $child) {
            $count = $this->checkTree($child, $inputs, $invalidSlot, $depth + 1, $count);
        }

        return $count;
    }

    /**
     * Checks a column role against the owning occurrence layout.
     * @param list<TableSource|DerivedSource> $inputs
     */
    public function column(ColumnReference|TextColumnReference|BooleanColumnReference|WideIntegerColumnReference $column, array $inputs, ?string $invalidSlot = null): void
    {
        $input = $inputs[$column->occurrence] ?? null;
        if ($column instanceof WideIntegerColumnReference) {
            Check::input(
                $input?->columnKind($column->slot) === ColumnKind::Int8,
                $invalidSlot ?? 'An int8 slot must belong to the SELECT source with its native kind.'
            );

            return;
        }
        if ($column instanceof BooleanColumnReference) {
            Check::input(
                $input?->columnKind($column->slot) === ColumnKind::Boolean,
                $invalidSlot ?? 'A Boolean slot must belong to the SELECT source with its native kind.'
            );

            return;
        }
        if ($column instanceof TextColumnReference) {
            Check::input(
                $input?->columnKind($column->slot) === ColumnKind::Text,
                $invalidSlot ?? 'A text slot must belong to the SELECT source with its native kind.'
            );

            return;
        }
        Check::input($this->hasSlot($input, $column->slot), $invalidSlot ?? 'Column slot must belong to the SELECT source.');
    }

    /**
     * Checks the ordinal against the input's owned layout.
     */
    public function hasSlot(TableSource|DerivedSource|null $input, int $slot): bool
    {
        return $input?->columnKind($slot) === ColumnKind::Int4;
    }
}
