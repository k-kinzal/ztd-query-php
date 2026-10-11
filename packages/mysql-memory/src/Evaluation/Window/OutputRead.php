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
 * Empty row subqueries inspect initial NULL state independently of value evaluation.
 * Value reads retain the original arguments and defaults. Completed windows retain their
 * last computed slot after OFFSET discards every output row. Initial values and warnings
 * are verified through SQL on MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class OutputRead implements Evaluable
{
    /**
     * @param Analytic $analytic The compiled window function
     * @param int $position Its output position in the query block's row
     * @param bool $materialized Whether the result uses a temporary window column
     */
    public function __construct(public readonly Analytic $analytic, public readonly int $position, public readonly bool $materialized = true)
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
     * Reads a computed value, or evaluates the retained input of an unread value function.
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
            WindowFunctionKind::Lead, WindowFunctionKind::Lag, WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue, WindowFunctionKind::NthValue => $this->initial($frame),
            null => null,
        };
    }

    /**
     * Inspects a window's NULL state without evaluating an unread value argument.
     */
    public function absent(Frame $frame): bool
    {
        if (array_key_exists($this->position, $frame->row)) {
            return $frame->row[$this->position] === null;
        }

        return $this->analytic->kind?->treatsNulls() ?? true;
    }

    /**
     * Reads the value for the initial row position, before the window consumes input.
     */
    public function initial(Frame $frame): int|float|string|null
    {
        $analytic = $this->analytic;
        $position = match ($analytic->kind) {
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => $analytic->count === 0 ? 0 : 1,
            WindowFunctionKind::NthValue => $analytic->count === 1 ? 0 : -1,
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue => 0,
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank,
            WindowFunctionKind::CumulativeDistribution, WindowFunctionKind::PercentRank, WindowFunctionKind::Tile, null => -1,
        };
        $argument = $analytic->arguments[$position] ?? null;

        return $argument === null ? null : Partition::converted($argument->evaluate($frame), $argument->domain(), $analytic->domain, $frame);
    }
}
