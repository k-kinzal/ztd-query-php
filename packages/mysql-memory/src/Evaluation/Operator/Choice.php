<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * CASE: the result of the first branch whose condition holds, or whose value equals the operand.
 *
 * The chosen result is converted to the domain the results aggregate to.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html#operator_case.
 *
 * @visibility MySqlMemory
 */
final class Choice implements Evaluable
{
    /**
     * @param Evaluable|null $operand The operand compared with each branch value, or null for conditions
     * @param list<array{Evaluable, Comparator|null, Evaluable}> $branches The condition or value, how it compares with the operand, and the result of each branch
     * @param Evaluable|null $else The result when no branch is chosen, or null for NULL
     * @param Domain $domain The domain of the result
     */
    public function __construct(public readonly ?Evaluable $operand, public readonly array $branches, public readonly ?Evaluable $else, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the result.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Chooses and evaluates a result for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $operand = $this->operand?->evaluate($frame);
        foreach ($this->branches as [$condition, $comparator, $result]) {
            $value = $condition->evaluate($frame);
            $chosen = $comparator === null ? Convert::toBool($value, $condition->domain(), $frame->context) === true : $comparator->compare($operand, $value, $frame->context) === 0;
            if ($chosen) {
                return Coerce::branch($result->evaluate($frame), $result->domain(), $this->domain, $frame->context);
            }
        }

        return $this->else === null ? null : Coerce::branch($this->else->evaluate($frame), $this->else->domain(), $this->domain, $frame->context);
    }
}
