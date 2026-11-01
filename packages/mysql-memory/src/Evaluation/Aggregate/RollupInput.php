<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Aggregate;

use MySqlMemory\Evaluation\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

/**
 * Captures the inputs that GROUP_CONCAT and COUNT DISTINCT share across ROLLUP levels.
 *
 * Other aggregates evaluate their argument separately for each level. Shared inputs
 * execute at the aggregate's position within the row, after earlier aggregates.
 * Verified through variable assignments in SQL on MySQL 8.4.
 *
 * @visibility MySqlMemory
 */
final class RollupInput
{
    /**
     * Captures a shared tuple and its sorting values, retaining NULL short-circuiting.
     *
     * @return array{values: list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>, order: list<int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null>}|null
     */
    public function capture(Accumulation $accumulation, Frame $frame): ?array
    {
        if ($accumulation->function !== null && !($accumulation->function === AggregateFunction::Count && $accumulation->distinct)) {
            return null;
        }
        if ($accumulation->input !== null) {
            return $accumulation->input->capture($accumulation, $frame);
        }
        $values = [];
        foreach ($accumulation->arguments as $argument) {
            $value = $argument->evaluate($frame);
            $values[] = $value;
            if ($value === null) {
                return ['values' => $values, 'order' => []];
            }
        }
        $order = [];
        foreach ($accumulation->order as [$key]) {
            $order[] = $key->evaluate($frame);
        }

        return ['values' => $values, 'order' => $order];
    }
}
