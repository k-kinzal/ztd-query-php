<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Outer;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Real;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

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
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Computes the result for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|string|null
    {
        if ($this->domain->kind === Kind::String) {
            return $this->bytes($frame);
        }
        $left = $this->operand($this->left, $this->left->evaluate($frame), $frame);
        if ($left === null) {
            return null;
        }
        if ($this->operator === null) {
            return ~$left;
        }
        $right = $this->operand($this->right, $this->right->evaluate($frame), $frame);
        if ($right === null) {
            return null;
        }

        return match ($this->operator) {
            ArithmeticOperator::BitOr => $left | $right,
            ArithmeticOperator::BitAnd => $left & $right,
            ArithmeticOperator::BitXor => $left ^ $right,
            ArithmeticOperator::ShiftLeft => $right < 0 || $right >= 64 ? 0 : $left << $right,
            ArithmeticOperator::ShiftRight, ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::Multiply, ArithmeticOperator::Divide, ArithmeticOperator::Modulo, ArithmeticOperator::IntegerDivide => $right < 0 || $right >= 64 ? 0 : ($left >> $right) & (PHP_INT_MAX >> ($right === 0 ? 0 : $right - 1) | ($right === 0 ? PHP_INT_MIN : 0)),
        };
    }

    /**
     * Reads the value of an operand as a 64-bit integer: a decimal or a double as a signed one, anything else as an unsigned one.
     *
     * A decimal beyond the signed range saturates with a warning. A double is rounded half to
     * even; beyond the signed range it saturates, silently for a literal, with a warning
     * (ER_TRUNCATED_WRONG_VALUE) for a column, and is an error (ER_DATA_OUT_OF_RANGE) for the
     * result of an arithmetic operator.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors, or a double result is beyond the range
     */
    public function operand(Evaluable $operand, int|float|string|null $value, Frame $frame): ?int
    {
        $domain = $operand->domain();
        if ($value !== null && $domain->kind === Kind::Decimal) {
            return Convert::decimalInteger((string) $value, $frame->context, false);
        }
        if ($value !== null && $domain->kind === Kind::Double) {
            $real = (float) $value;
            $rounded = round($real, 0, PHP_ROUND_HALF_EVEN);
            if ($rounded >= 9223372036854775807.0 || $rounded < -9223372036854775808.0) {
                $origin = $operand;
                while ($origin instanceof Retyped) {
                    $origin = $origin->evaluable;
                }
                if ($origin instanceof Arithmetic || $origin instanceof Minus) {
                    throw ErrorCode::DataOutOfRange->error('BIGINT', $origin->text);
                }
                if ($origin instanceof ColumnRead || $origin instanceof Outer) {
                    $frame->context->warning(ErrorCode::TruncatedWrongValue, 'INTEGER', Real::format($real));
                }
            }

            return Integer::fromReal($real, false);
        }

        return Convert::toInteger($value, $domain, $frame->context, true);
    }

    /**
     * Evaluates the operator on binary strings: byte by byte, or shifting the bits within the length of the string.
     *
     * @throws \MySqlMemory\Error\SqlError When the operands of AND, OR or XOR differ in length
     */
    public function bytes(Frame $frame): ?string
    {
        $left = $this->left->evaluate($frame);
        if ($left === null) {
            return null;
        }
        $left = (string) Convert::toText($left, $this->left->domain());
        if ($this->operator === null) {
            return ~$left;
        }
        $right = $this->right->evaluate($frame);
        if ($right === null) {
            return null;
        }
        if ($this->operator === ArithmeticOperator::ShiftLeft || $this->operator === ArithmeticOperator::ShiftRight) {
            return $this->shifted($left, (int) $this->operand($this->right, $right, $frame), $this->operator === ArithmeticOperator::ShiftLeft);
        }
        $right = (string) Convert::toText($right, $this->right->domain());
        if (strlen($left) !== strlen($right)) {
            throw ErrorCode::BitwiseOperandsSize->error();
        }

        return match ($this->operator) {
            ArithmeticOperator::BitAnd => $left & $right,
            ArithmeticOperator::BitXor => $left ^ $right,
            ArithmeticOperator::BitOr, ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::Multiply, ArithmeticOperator::Divide, ArithmeticOperator::Modulo, ArithmeticOperator::IntegerDivide => $left | $right,
        };
    }

    /**
     * Shifts the bits of a binary string by a count, keeping its length.
     */
    public function shifted(string $bytes, int $count, bool $left): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $width = strlen($bits);
        $count = $count < 0 || $count > $width ? $width : $count;
        $shifted = $left ? substr($bits, $count) . str_repeat('0', $count) : str_repeat('0', $count) . substr($bits, 0, $width - $count);
        $result = '';
        foreach (str_split($shifted, 8) as $octet) {
            $result .= chr((int) bindec($octet));
        }

        return $result;
    }

}
