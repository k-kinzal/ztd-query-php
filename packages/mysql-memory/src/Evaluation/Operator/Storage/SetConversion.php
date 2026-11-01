<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Storage;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Stores a UNION operand in its resolved result field.
 *
 * Unlike an explicit integer CAST, storage clamps a signedness overflow. Legacy
 * decimal fields can also be narrower than their operand value; storing them
 * saturates without issuing a CAST warning. Conversion warnings produced while
 * reading the original operand are retained. Copying an integer field of the
 * same storage type retains its bits, including a differing signedness; the
 * projection origin supplies this distinction independently of SQL text. Observed through native 5.6 and
 * 5.7 UNION, UNION ALL and derived-table consumers.
 *
 * @visibility MySqlMemory
 */
final class SetConversion implements Evaluable
{
    /**
     * @param Evaluable $operand The original result value
     * @param Domain $domain The resolved set column
     * @param bool $copied Whether the operand directly copies a stored field
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain, public readonly bool $copied = false)
    {
    }

    /**
     * Answers the resolved result field.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Converts the operand once, retaining any warnings from its evaluation.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $from = $this->operand->domain();
        if ($this->domain->kind !== Kind::Decimal && !($this->domain->kind === Kind::Integer && $from->kind === Kind::Integer)) {
            return (new Conversion($this->operand, $this->domain, null, $this->domain->collation->bytes() ? 'BINARY' : 'CHAR'))->evaluate($frame);
        }
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        if ($this->domain->kind === Kind::Decimal) {
            return $this->decimal((string) Convert::operandDecimal($value, $this->operand, $frame->context));
        }

        return $this->integer((int) \MySqlMemory\Evaluation\ExactDecimal::raw($value), $from);
    }

    /**
     * Saturates a value whose sign cannot be represented by the target field.
     */
    public function integer(int $value, Domain $from): int
    {
        if ($this->copied && $from->field === $this->domain->field) {
            return $value;
        }
        $bits = match ($this->domain->field) {
            Field::Tiny => 8, Field::Short => 16, Field::Int24 => 24, Field::Long => 32, Field::Decimal, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::LongLong, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => 64,
        };
        $maximum = $bits === 64 ? PHP_INT_MAX : (1 << ($bits - (int) !$this->domain->unsigned)) - 1;
        if ($value < 0 && ($from->unsigned !== $this->domain->unsigned || ($from->unsigned && $bits < 64))) {
            return $from->unsigned ? $maximum : 0;
        }
        if ($bits === 64) {
            return $value;
        }

        return max($this->domain->unsigned ? 0 : -$maximum - 1, min($maximum, $value));
    }

    /**
     * Rounds and saturates the decimal in the set column's precision.
     */
    public function decimal(string $value): string
    {
        $rounded = Decimal::round($value, $this->domain->decimals);
        $digits = $this->domain->precision() - $this->domain->decimals;
        if (Decimal::integerDigits($rounded) <= $digits || trim(explode('.', ltrim($rounded, '-'))[0], '0') === '') {
            return $rounded;
        }
        $largest = str_repeat('9', max(1, $digits)) . ($this->domain->decimals > 0 ? '.' . str_repeat('9', $this->domain->decimals) : '');

        return str_starts_with($rounded, '-') ? '-' . $largest : $largest;
    }
}
