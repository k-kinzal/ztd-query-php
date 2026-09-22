<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Statement\CompoundStatement;

/**
 * Validates operands of a binary set operation in the selected SQL language.
 * @visibility SqlSemantics
 */
final class SetOperands
{
    /**
     * @throws InvalidStructure
     */
    public static function check(Dialect $dialect, BoundQuery $left, BoundQuery $right): void
    {
        if ($left->origin->dialect !== $dialect || $right->origin->dialect !== $dialect) {
            throw new InvalidStructure('Set operands must use the statement dialect.');
        }
        $leftWidth = RowShape::width($left);
        $rightWidth = RowShape::width($right);
        if ($leftWidth !== null && $rightWidth !== null && $leftWidth !== $rightWidth) {
            throw new InvalidStructure('A set operation requires operands with equal result widths.');
        }
        if ($dialect !== Dialect::Sqlite) {
            return;
        }
        if ($right instanceof CompoundStatement) {
            throw new InvalidStructure('SQLite set operations associate to the left; a right compound operand requires an explicit derived relation.');
        }
        foreach ([$left, $right] as $operand) {
            if ($operand->ctes !== null || $operand->orderBy !== [] || $operand->limit !== null || $operand->offset !== null) {
                throw new InvalidStructure('SQLite set operands cannot own WITH, ORDER BY or pagination clauses.');
            }
        }
    }
}
