<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;

/**
 * Evaluates tuple aggregates at their query block's input phases.
 *
 * Explicit groups read complete tuples before rejecting NULL rows. An implicit
 * COUNT DISTINCT omits constant fields; an implicit GROUP_CONCAT reads varying
 * fields and sort keys before its deferred constant expressions.
 * Verified through SQL results and user variables on MySQL 8.0, 8.4 and 9.1.
 *
 * @visibility MySqlMemory
 */
final class TupleInput
{
    /**
     * @param bool $grouped Whether the query has explicit grouping keys
     * @param bool $count Whether this is COUNT DISTINCT
     * @param list<int> $constants Positions that do not read row fields
     * @param list<Evaluable> $checks Constant arguments checked once for NULL
     * @param bool $disabled Whether preparation found a NULL constant
     */
    public function __construct(public readonly bool $grouped, public readonly bool $count, public readonly array $constants = [], public readonly array $checks = [], public readonly bool $disabled = false)
    {
    }

    /**
     * Tests constant arguments before reading any tuple, retaining the outcome.
     */
    public function enabled(Frame $frame): bool
    {
        if ($this->disabled) {
            return false;
        }
        if (!isset($frame->context->kept[$this])) {
            $enabled = 1;
            foreach ($this->checks as $check) {
                if ($check->evaluate($frame) === null) {
                    $enabled = 0;
                    break;
                }
            }
            $kept = $frame->context->kept;
            $kept[$this] = [$enabled];
        }

        return $frame->context->kept[$this][0] === 1;
    }

    /**
     * Captures every varying field before deferred expressions or NULL rejection.
     *
     * @return array{values: list<int|float|string|null>, order: list<int|float|string|null>}
     */
    public function capture(Accumulation $accumulation, Frame $frame): array
    {
        if (!$this->enabled($frame)) {
            return ['values' => [null], 'order' => []];
        }
        $order = [];
        foreach ($accumulation->order as [$key]) {
            $order[] = $key->evaluate($frame);
        }
        $values = [];
        foreach ($accumulation->arguments as $position => $argument) {
            $deferred = ($this->count || !$this->grouped) && in_array($position, $this->constants, true);
            $values[] = $deferred ? 0 : $argument->evaluate($frame);
        }
        return ['values' => $values, 'order' => $order];
    }

    /**
     * Reads deferred concatenation expressions when an accepted tuple is emitted.
     *
     * @param list<int|float|string> $values The stored tuple fields
     * @return list<int|float|string|null>
     */
    public function complete(Accumulation $accumulation, Frame $frame, array $values): array
    {
        if (!$this->grouped && !$this->count) {
            foreach ($this->constants as $position) {
                $values[$position] = $accumulation->arguments[$position]->evaluate($frame);
            }
        }

        return array_values($values);
    }
}
