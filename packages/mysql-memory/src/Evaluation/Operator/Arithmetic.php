<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use Override;
use SqlSemantics\Platform\MySql\Rules\Typing\Numbers;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The arithmetic operators `+`, `-`, `*`, `/`, DIV and `%` over two operands.
 *
 * The operands are read in the kind of the result. A division by zero is NULL with a warning
 * (ER_DIVISION_BY_ZERO), or an error when a write runs under a strict mode with
 * ERROR_FOR_DIVISION_BY_ZERO. An integer result outside the BIGINT range is an error
 * (ER_DATA_OUT_OF_RANGE).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Arithmetic implements Evaluable
{
    /**
     * @param ArithmeticOperator $operator The operator
     * @param Evaluable $left The left operand
     * @param Evaluable $right The right operand
     * @param Domain $domain The domain of the result
     * @param string $text The expression as the server prints it in messages
     */
    public function __construct(
        public readonly ArithmeticOperator $operator,
        public readonly Evaluable $left,
        public readonly Evaluable $right,
        public readonly Domain $domain,
        public readonly string $text,
    ) {
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
     *
     * In double precision or as an integer both operands are read, each converted in its turn,
     * before a NULL makes the result NULL; as an exact decimal, and for DIV, a NULL left operand
     * leaves the right one unread.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        if ($this->operator === ArithmeticOperator::IntegerDivide) {
            return $this->integerDivide($frame);
        }
        if ($this->domain->kind === Kind::Decimal) {
            $left = Convert::toDecimal($this->left->evaluate($frame), $this->left->domain(), $frame->context);
            if ($left === null) {
                return null;
            }
            $right = Convert::toDecimal($this->right->evaluate($frame), $this->right->domain(), $frame->context);

            return $right === null ? null : $this->decimal($left, $right, $frame);
        }
        $left = $this->operand($this->left, $frame);
        $right = $this->operand($this->right, $frame);
        if ($left === null || $right === null) {
            return null;
        }

        return $this->domain->kind === Kind::Double ? $this->real((float) $left, (float) $right, $frame) : $this->integer((int) $left, (int) $right, $frame);
    }

    /**
     * Reads an operand for a row in the kind of a double or integer result.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement raises warnings as errors
     */
    public function operand(Evaluable $operand, Frame $frame): int|float|null
    {
        $value = $operand->evaluate($frame);

        return $this->domain->kind === Kind::Double ? Convert::toDouble($value, $operand->domain(), $frame->context) : Convert::toInteger($value, $operand->domain(), $frame->context);
    }

    /**
     * Computes the result in double precision.
     */
    public function real(float $left, float $right, Frame $frame): ?float
    {
        if (($this->operator === ArithmeticOperator::Divide || $this->operator === ArithmeticOperator::Modulo) && $right === 0.0) {
            return $this->byZero($frame);
        }
        $result = match ($this->operator) {
            ArithmeticOperator::Plus => $left + $right,
            ArithmeticOperator::Minus => $left - $right,
            ArithmeticOperator::Multiply => $left * $right,
            ArithmeticOperator::Divide => $left / $right,
            ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight, ArithmeticOperator::Modulo, ArithmeticOperator::IntegerDivide, ArithmeticOperator::BitXor => fmod($left, $right),
        };
        if (is_infinite($result) || is_nan($result)) {
            throw DataError::DataOutOfRange->error('DOUBLE', $this->text);
        }

        return $result;
    }

    /**
     * Computes the result as an exact decimal.
     */
    public function decimal(string $left, string $right, Frame $frame): ?string
    {
        $result = match ($this->operator) {
            ArithmeticOperator::Plus => Decimal::add($left, $right),
            ArithmeticOperator::Minus => Decimal::subtract($left, $right),
            ArithmeticOperator::Multiply => Decimal::multiply($left, $right),
            ArithmeticOperator::Divide => Decimal::divide($left, $right, $this->domain->decimals),
            ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight, ArithmeticOperator::Modulo, ArithmeticOperator::IntegerDivide, ArithmeticOperator::BitXor => Decimal::modulo($left, $right),
        };
        if ($result === null) {
            return $this->byZero($frame);
        }
        if (Decimal::integerDigits($result) > Decimal::MAX_PRECISION - $this->domain->decimals) {
            throw DataError::DataOutOfRange->error('DECIMAL', $this->text);
        }

        return Decimal::round($result, $this->domain->decimals);
    }

    /**
     * Computes the result as a 64-bit integer, signed or unsigned as the result domain is.
     */
    public function integer(int $left, int $right, Frame $frame): ?int
    {
        $leftText = Decimal::numeric(Integer::text($left, $this->left->domain()->numericBytes || ($this->left->domain()->unsigned && (new Numbers())->operand($this->left->domain()->resolved()) === Kind::Integer)));
        $rightText = Decimal::numeric(Integer::text($right, $this->right->domain()->numericBytes || ($this->right->domain()->unsigned && (new Numbers())->operand($this->right->domain()->resolved()) === Kind::Integer)));
        if ($this->operator === ArithmeticOperator::Modulo) {
            if ($rightText === '0') {
                return $this->byZero($frame);
            }
            $result = bcmod($leftText, $rightText, 0);
        } else {
            $result = match ($this->operator) {
                ArithmeticOperator::Plus => bcadd($leftText, $rightText, 0),
                ArithmeticOperator::Minus => bcsub($leftText, $rightText, 0),
                ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight, ArithmeticOperator::Multiply, ArithmeticOperator::Divide, ArithmeticOperator::IntegerDivide, ArithmeticOperator::BitXor => bcmul($leftText, $rightText, 0),
            };
        }

        return $this->bounded($result);
    }

    /**
     * Computes DIV: the quotient truncated toward zero; a NULL left operand leaves the right one unread.
     */
    public function integerDivide(Frame $frame): ?int
    {
        $leftText = Convert::operandDecimal($this->left->evaluate($frame), $this->left, $frame->context);
        if ($leftText === null) {
            return null;
        }
        $rightText = Convert::operandDecimal($this->right->evaluate($frame), $this->right, $frame->context);
        if ($rightText === null) {
            return null;
        }
        if (Decimal::compare($rightText, '0') === 0) {
            return $this->byZero($frame);
        }

        return $this->bounded(bcdiv($leftText, $rightText, 0));
    }

    /**
     * Answers an integer text as an int, or raises ER_DATA_OUT_OF_RANGE outside the range of the result.
     */
    public function bounded(string $result): int
    {
        if ($this->domain->unsigned ? !Integer::unsignedRange($result) : !Integer::signedRange($result)) {
            throw DataError::DataOutOfRange->error($this->domain->unsigned ? 'BIGINT UNSIGNED' : 'BIGINT', $this->text);
        }

        return $this->domain->unsigned ? Integer::fromUnsignedText($result) : (int) $result;
    }

    /**
     * Answers the result of a division by zero: NULL with a warning, or an error in a strict write.
     *
     * @return null
     */
    public function byZero(Frame $frame): mixed
    {
        $context = $frame->context;
        if ($context->modes->has('ERROR_FOR_DIVISION_BY_ZERO')) {
            $context->warn(DataError::DivisionByZero);
        }

        return null;
    }
}
