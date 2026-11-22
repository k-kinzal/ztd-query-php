<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Validates signed predicates against the exact table occurrences at their boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OccurrenceScope
{
    /**
     * Closes each predicate over the owning occurrence layout and bounds its expanded tree.
     * @param list<TableSource> $inputs
     */
    public function predicate(
        BooleanLiteral|IntegerComparison|LogicalPredicate|NullLiteral|NullTest|null $condition,
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
     * @param list<TableSource> $inputs The visible input occurrences
     */
    public function checkTree(
        BooleanLiteral|ColumnReference|IntegerComparison|IntegerLiteral|LogicalPredicate|NullLiteral|NullTest $operand,
        array $inputs,
        string $invalidSlot,
        int $depth,
        int $count,
    ): int {
        Check::input($depth <= 32 && ++$count <= 8192, 'Predicate depth or expanded node count exceeds the IR profile.');
        if ($operand instanceof ColumnReference) {
            Check::input(isset($inputs[$operand->occurrence]->columns[$operand->slot]), $invalidSlot);
        }
        $children = match (true) {
            $operand instanceof LogicalPredicate => $operand->right === null ? [$operand->left] : [$operand->left, $operand->right],
            $operand instanceof IntegerComparison => [$operand->left, $operand->right],
            $operand instanceof NullTest => [$operand->operand],
            $operand instanceof BooleanLiteral, $operand instanceof ColumnReference, $operand instanceof IntegerLiteral, $operand instanceof NullLiteral => [],
        };
        foreach ($children as $child) {
            $count = $this->checkTree($child, $inputs, $invalidSlot, $depth + 1, $count);
        }

        return $count;
    }
}
