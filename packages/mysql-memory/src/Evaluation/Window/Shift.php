<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * A number moved by the offset of a boundary of a RANGE frame: where the boundary lies for the ordering value of a row.
 *
 * The value is moved in double arithmetic when the domain is a double, and in exact decimal
 * arithmetic otherwise.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility MySqlMemory
 */
final class Shift implements Evaluable
{
    /**
     * @param Evaluable $operand The ordering value
     * @param string $amount The offset, as a decimal number
     * @param bool $subtract Whether the offset is taken away
     * @param Domain $domain The domain of the result: a double, or a decimal
     */
    public function __construct(public readonly Evaluable $operand, public readonly string $amount, public readonly bool $subtract, public readonly Domain $domain)
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
     * Moves the value of the row, or answers NULL for NULL.
     */
    #[Override]
    public function evaluate(Frame $frame): float|string|null
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if ($this->domain->kind === Kind::Double) {
            $number = (float) Convert::toDouble($value, $this->operand->domain(), $frame->context);

            return $this->subtract ? $number - (float) $this->amount : $number + (float) $this->amount;
        }
        $number = (string) Convert::toDecimal($value, $this->operand->domain(), $frame->context);

        return $this->subtract ? Decimal::subtract($number, $this->amount) : Decimal::add($number, $this->amount);
    }
}
