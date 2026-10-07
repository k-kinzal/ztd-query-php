<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * NOT and `!`: 1 for a false operand, 0 for a true one, NULL for NULL.
 *
 * @visibility MySqlMemory
 */
final class Negation implements Evaluable
{
    /**
     * @param Evaluable $operand The operand
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain)
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
     * Negates the truth value of the operand for a row.
     */
    #[\Override]
    public function evaluate(Frame $frame): ?int
    {
        $value = Convert::toBool($this->operand->evaluate($frame), $this->operand->domain(), $frame->context);

        return $value === null ? null : ($value ? 0 : 1);
    }
}
