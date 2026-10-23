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
     * @param bool $foldUnread Whether an unread aggregate evaluates its retained input
     * @param bool $averageTotal Whether an unread AVG over a table exposes its running total
     */
    public function __construct(public readonly Analytic $analytic, public readonly int $position, public readonly bool $foldUnread = true, public readonly bool $averageTotal = false)
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
        if (array_key_exists($this->position, $frame->windowSlots)) {
            return $frame->windowSlots[$this->position];
        }

        return match ($this->analytic->kind) {
            WindowFunctionKind::PercentRank => 0.0,
            WindowFunctionKind::CumulativeDistribution => 1.0,
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank, WindowFunctionKind::Tile => 1,
            WindowFunctionKind::Lead, WindowFunctionKind::Lag, WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue, WindowFunctionKind::NthValue => $this->initial($frame),
            null => $this->aggregate($frame),
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
        if (array_key_exists($this->position, $frame->windowSlots)) {
            return $frame->windowSlots[$this->position] === null;
        }

        return $this->analytic->kind?->treatsNulls() ?? $this->analytic->accumulation?->domain->nullable ?? true;
    }

    /**
     * Reads initial aggregate state. Direct empty projections fold once per value read;
     * a window that ran over a table keeps the identity until it computes an output.
     * NULL inspection does not fold. Verified through SQL on MySQL 8.0, 8.4 and 9.1.
     */
    public function aggregate(Frame $frame): int|float|string|null
    {
        $accumulation = $this->analytic->accumulation;
        if ($accumulation === null) {
            return null;
        }
        if (!isset($frame->unreadWindows[$this->position])) {
            if ($this->averageTotal && $accumulation->function === \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Average) {
                $accumulation = new \MySqlMemory\Evaluation\Aggregate\Accumulation(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum, $accumulation->arguments, $accumulation->distinct, $accumulation->domain);
            }
            $frame->unreadWindows[$this->position] = $accumulation->start();
        }
        $fold = $frame->unreadWindows[$this->position];
        if ($this->foldUnread) {
            $fold->add($frame);
        }

        return $fold->result($frame);
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
