<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

/**
 * Reads a window's output slot, preserving its initial state before any row reaches the projection.
 *
 * An empty row subquery can inspect rank functions before execution or after OFFSET has
 * discarded every output row. The latter retains the last computed slot. Initial values
 * are verified through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class OutputRead implements Evaluable
{
    /**
     * @param Analytic $analytic The compiled window function
     * @param int $position Its output position in the query block's row
     */
    public function __construct(public readonly Analytic $analytic, public readonly int $position)
    {
    }

    /**
     * Answers the window function's result domain.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->analytic->domain;
    }

    /**
     * Reads a computed value, including NULL, or the initial value of an unread rank function.
     *
     * @phpstan-pure
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        if (array_key_exists($this->position, $frame->row)) {
            return $frame->row[$this->position];
        }

        return match ($this->analytic->kind) {
            WindowFunctionKind::PercentRank => 0.0,
            WindowFunctionKind::CumulativeDistribution => 1.0,
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank, WindowFunctionKind::Tile => 1,
            WindowFunctionKind::Lead, WindowFunctionKind::Lag, WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue, WindowFunctionKind::NthValue, null => null,
        };
    }
}
