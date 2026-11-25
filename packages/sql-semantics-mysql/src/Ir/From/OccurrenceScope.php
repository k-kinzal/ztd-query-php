<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\TextUpdateAssignment;
use SqlSemantics\Platform\MySql\Ir\Mutation\UpdateAssignment;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Validates native predicates against the exact table occurrences at their boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OccurrenceScope
{
    /**
     * Closes each predicate over the owning occurrence layout and bounds its expanded tree.
     * @param list<TableSource|DerivedSource> $inputs
     */
    public function predicate(
        BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|null $condition,
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
        BooleanLiteral|CollatedText|ColumnReference|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|IntegerLiteral|LogicalPredicate|NullLiteral|NullTest|RangeIntegerLiteral|SignedCase|IntegralColumnReference|TextColumnReference|TextLiteral $operand,
        array $inputs,
        string $invalidSlot,
        int $depth,
        int $count,
    ): int {
        Check::input($depth <= 32 && ++$count <= 8192, 'Predicate depth or expanded node count exceeds the IR profile.');
        if ($operand instanceof ColumnReference) {
            Check::input($this->hasSlot($inputs[$operand->occurrence] ?? null, $operand->slot), $invalidSlot);
        }
        if ($operand instanceof IntegralColumnReference) {
            $input = $inputs[$operand->occurrence] ?? null;
            Check::input(
                $input instanceof TableSource && ($input->columnTypes[$operand->slot] ?? null) instanceof IntegralSlot,
                $invalidSlot,
            );
        }
        if ($operand instanceof TextColumnReference) {
            $this->textColumn($operand, $inputs);
        }
        $children = match (true) {
            $operand instanceof LogicalPredicate => $operand->right === null ? [$operand->left] : [$operand->left, $operand->right],
            $operand instanceof IntegerComparison, $operand instanceof TextComparison => [$operand->left, $operand->right],
            $operand instanceof SignedCase => $operand->children(),
            $operand instanceof IntegerMembership => [$operand->subject, ...$operand->alternatives],
            $operand instanceof IntegerRange => [$operand->subject, $operand->lower, $operand->upper],
            $operand instanceof NullTest => [$operand->operand],
            $operand instanceof CollatedText => [$operand->operand],
            $operand instanceof BooleanLiteral, $operand instanceof ColumnReference, $operand instanceof IntegralColumnReference, $operand instanceof IntegerLiteral, $operand instanceof NullLiteral, $operand instanceof RangeIntegerLiteral, $operand instanceof TextColumnReference, $operand instanceof TextLiteral => [],
        };
        foreach ($children as $child) {
            $count = $this->checkTree($child, $inputs, $invalidSlot, $depth + 1, $count);
        }

        return $count;
    }

    /**
     * Checks the ordinal against the input's owned layout.
     */
    public function hasSlot(TableSource|DerivedSource|null $input, int $slot): bool
    {
        return $input instanceof DerivedSource ? $input->columnType($slot) instanceof ColumnKind
            : ($input->columnTypes[$slot] ?? null) instanceof ColumnKind;
    }

    /**
     * Matches the assignment's closed role against the physical target layout.
     */
    public function updateAssignment(UpdateAssignment|TextUpdateAssignment $assignment, TableSource $target): void
    {
        $value = $assignment->value;
        if ($assignment instanceof TextUpdateAssignment) {
            $this->textColumn($assignment->target, [$target]);
            if ($value instanceof TextColumnReference) {
                $this->textColumn($value, [$target]);
            }

            return;
        }

        $slot = $assignment->target->slot;
        Check::input(($target->columnTypes[$slot] ?? null) instanceof ColumnKind, 'An UPDATE write slot must belong to the target.');
        Check::input(!$value instanceof ColumnReference || ($target->columnTypes[$value->slot] ?? null) instanceof ColumnKind, 'An UPDATE read slot must belong to the target.');
    }

    /**
     * Validates a VARCHAR reference in the exact physical or derived occurrence.
     * @param list<TableSource|DerivedSource> $inputs
     */
    public function textColumn(TextColumnReference $reference, array $inputs): void
    {
        $input = $inputs[$reference->occurrence] ?? null;
        if ($input instanceof DerivedSource) {
            Check::input($input->columnType($reference->slot) instanceof Varchar, 'A derived VARCHAR reference must read a native VARCHAR output.');

            return;
        }
        Check::input(
            $input instanceof TableSource && ($input->columnTypes[$reference->slot] ?? null) instanceof Varchar,
            'A VARCHAR slot must belong to a visible physical input occurrence.',
        );
    }
}
