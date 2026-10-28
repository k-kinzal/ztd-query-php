<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Leaf;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\NumericText;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * A bound value read in its prepared domain, retaining its actual decimal precision.
 *
 * Modern numeric parameters first recognize a complete numeric string. An integer
 * context can therefore round a decimal string, unlike an ordinary string CAST.
 * Source: MySQL 8.4.7 sql/item.cc, Item_param::convert_value and Item_param::val_int.
 *
 * @visibility MySqlMemory
 */
final class ParameterRead implements Evaluable
{
    /**
     * @param int|float|string|null $value The current argument
     * @param Domain $actual The supplied value type
     * @param Domain $derived The retained parameter type
     * @param bool $modern Whether preparation derives parameter types
     */
    public function __construct(public readonly int|float|string|null $value, public readonly Domain $actual, public readonly Domain $derived, public readonly bool $modern)
    {
    }

    /**
     * Answers the retained type, independently of the current argument.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->derived;
    }

    /**
     * Converts the argument without padding decimals to the metadata scale.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $this->value;
        $actual = $this->actual;
        if ($value === null || $actual->kind === $this->derived->kind) {
            return $value;
        }
        if ($this->modern && $actual->kind === Kind::String && in_array($this->derived->kind, [Kind::Integer, Kind::Decimal, Kind::Double], true)) {
            $text = trim(Convert::readable((string) $value, $actual));
            if (is_numeric($text) && is_finite((float) $text)) {
                $exponent = preg_match('/[eE]([+-]?[0-9]+)/', $text, $match) === 1 ? (int) $match[1] : 0;
                if ($this->derived->kind !== Kind::Double && strlen($text) <= Decimal::MAX_PRECISION && abs($exponent) <= Decimal::MAX_PRECISION) {
                    $value = NumericText::exact($text)->number;
                    $actual = Domain::decimal(65, min(30, Decimal::scale($value)));
                } else {
                    $value = (float) $text;
                    $actual = Domain::double();
                }
            }
        }

        return match ($this->derived->kind) {
            Kind::Integer => Convert::toInteger($value, $actual, $frame->context, $this->derived->unsigned),
            Kind::Decimal => Convert::toDecimal($value, $actual, $frame->context),
            Kind::Double => Convert::toDouble($value, $actual, $frame->context),
            Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => (new \MySqlMemory\Evaluation\Operator\Conversion(new Constant($actual, $value), $this->derived, null, $this->derived->resolved()->name()))->evaluate($frame),
        };
    }
}
