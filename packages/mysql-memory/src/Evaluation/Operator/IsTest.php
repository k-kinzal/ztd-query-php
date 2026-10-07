<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * IS [NOT] NULL, IS [NOT] TRUE, IS [NOT] FALSE and IS [NOT] UNKNOWN: never NULL.
 *
 * @visibility MySqlMemory
 */
final class IsTest implements Evaluable
{
    /**
     * @param Evaluable $operand The operand
     * @param bool|null $truth The truth value tested, or null for NULL and UNKNOWN
     * @param bool $negated Whether NOT is written
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly Evaluable $operand, public readonly ?bool $truth, public readonly bool $negated, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[\Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Tests the operand for a row.
     */
    #[\Override]
    public function evaluate(Frame $frame): int
    {
        $value = $this->operand->evaluate($frame);
        $holds = $this->truth === null ? $value === null : Convert::toBool($value, $this->operand->domain(), $frame->context) === $this->truth;

        return $holds !== $this->negated ? 1 : 0;
    }
}
