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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Unary minus.
 *
 * Negating the smallest BIGINT, or an unsigned value above it, is an error (ER_DATA_OUT_OF_RANGE).
 *
 * @visibility MySqlMemory
 */
final class Minus implements Evaluable
{
    /**
     * @param Evaluable $operand The operand
     * @param Domain $domain The domain of the result
     * @param string $text The expression as the server prints it
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain, public readonly string $text)
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
     * Negates the operand for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $this->operand->domain();

        return match ($this->domain->kind) {
            Kind::Integer => $this->integer((int) Convert::toInteger($value, $domain, $frame->context), $domain->unsigned),
            Kind::Decimal => Decimal::negate((string) Convert::toDecimal($value, $domain, $frame->context)),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => -(float) Convert::toDouble($value, $domain, $frame->context),
        };
    }

    /**
     * Negates an integer, within the signed range.
     */
    public function integer(int $value, bool $unsigned): int
    {
        $text = Integer::text($value, $unsigned);
        $negated = Decimal::canonical(str_starts_with($text, '-') ? substr($text, 1) : '-' . $text);
        if (!Integer::signedRange($negated)) {
            throw DataError::DataOutOfRange->error('BIGINT', $this->text);
        }

        return (int) $negated;
    }
}
