<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Statement\Scalar;

/**
 * Retains the early condition of differently sized written row comparisons.
 *
 * MySQL 5.7 and modern releases check the outer widths during
 * contextualization of a completed statement. MySQL 5.6, nested row shapes,
 * scalar/row pairs and subquery widths wait for preparation. Verified through
 * ordinary SQL and stored definitions against all five release families.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ComparisonWidth
{
    /**
     * Records an early condition only when both operands are written rows.
     */
    public function notice(Scalar|RowOperand $left, Scalar|RowOperand $right, Derivation $derivation): void
    {
        $problem = $this->problem($left, $right, $derivation->context->profile->grammar);
        if ($problem !== null) {
            $derivation->warn(new ParseFailure($problem, true, NoticePhase::Contextualization));
        }
    }

    /**
     * Finds an outer width contradiction checked before expression preparation.
     */
    public function problem(Scalar|RowOperand $left, Scalar|RowOperand $right, GrammarRelease $release): ?OperandColumns
    {
        $one = $this->row($left);
        $two = $this->row($right);
        return $release === GrammarRelease::MySql5651 || $one === null || $two === null || count($one->elements) === count($two->elements) ? null : new OperandColumns(count($one->elements), count($two->elements));
    }

    /**
     * Finds a written row through grouping, without inspecting subquery output.
     */
    public function row(Scalar|RowOperand $operand): ?Row
    {
        while ($operand instanceof Grouped || $operand instanceof \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow) {
            $operand = $operand->operand;
        }
        return $operand instanceof Row ? $operand : null;
    }
}
