<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Search;

use Deriver\Deriver\Evaluator\Term;
use WeakMap;

/**
 * Distinguishes an unfinished dependency from an ordinary unknown application input.
 * @visibility parent
 */
final class Interruption
{
    /**
     * Finds a retained stopping reason without losing shared dependency identity.
     */
    public function reason(Term $value): ?string
    {
        $pending = [$value];
        $seen = new WeakMap();
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            $reason = $node->attributes['reason'] ?? null;
            if (is_string($reason) && in_array($reason, ['BUDGET_EXCEEDED', 'DEPTH_LIMIT', 'RECURSION_LIMIT', 'ITERATION_LIMIT', 'ENUMERATION_LIMIT'], true)) {
                return $reason;
            }
            array_push($pending, ...array_values($node->operands));
        }
        return null;
    }

    /**
     * Keeps unfinished path feasibility attached to the value it controls.
     */
    public function guard(Term $value, Term $condition): Term
    {
        $reason = $this->reason($condition);
        return $reason === null ? $value : new Term(\Deriver\Deriver\Evaluator\TermKind::ControlledValue, operands: [$value, $condition], attributes: ['reason' => $reason]);
    }
}
