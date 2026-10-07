<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;

/**
 * The bit operators `|`, `&`, `^`, `<<`, `>>` and `~` over 64-bit unsigned integers.
 *
 * A shift by 64 bits or more is 0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/bit-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Bits implements Evaluable
{
    /**
     * @param ArithmeticOperator|null $operator The operator, or null for `~`
     * @param Evaluable $left The left operand, or the operand of `~`
     * @param Evaluable $right The right operand; the operand again for `~`
     * @param Domain $domain The domain of the result
     * @param string $text The expression as the server prints it
     */
    public function __construct(public readonly ?ArithmeticOperator $operator, public readonly Evaluable $left, public readonly Evaluable $right, public readonly Domain $domain, public readonly string $text)
    {
    }

    /**
     * Answers the domain of the result.
     */
    #[\Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Computes the result for a row.
     */
    #[\Override]
    public function evaluate(Frame $frame): ?int
    {
        $left = Convert::toInteger($this->left->evaluate($frame), $this->left->domain(), $frame->context, true);
        if ($left === null) {
            return null;
        }
        if ($this->operator === null) {
            return ~$left;
        }
        $right = Convert::toInteger($this->right->evaluate($frame), $this->right->domain(), $frame->context, true);
        if ($right === null) {
            return null;
        }

        return match ($this->operator) {
            ArithmeticOperator::BitOr => $left | $right,
            ArithmeticOperator::BitAnd => $left & $right,
            ArithmeticOperator::BitXor => $left ^ $right,
            ArithmeticOperator::ShiftLeft => $right < 0 || $right >= 64 ? 0 : $left << $right,
            default => $right < 0 || $right >= 64 ? 0 : ($left >> $right) & (PHP_INT_MAX >> ($right === 0 ? 0 : $right - 1) | ($right === 0 ? PHP_INT_MIN : 0)),
        };
    }
}
