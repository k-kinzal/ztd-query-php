<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * CAST(value AS type): the value converted into the domain of the type.
 *
 * A string that is not a date or time converts to NULL with a warning. A string longer than a
 * CHAR(N) or BINARY(N) target is cut with a warning. A negative number cast to UNSIGNED takes
 * its two's complement with a warning.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Conversion implements Evaluable
{
    /**
     * @param Evaluable $operand The value converted
     * @param Domain $domain The domain of the target
     * @param int|null $limit The length of a CHAR(N) or BINARY(N) target
     * @param string $target The keyword of the target, for warnings
     */
    public function __construct(public readonly Evaluable $operand, public readonly Domain $domain, public readonly ?int $limit, public readonly string $target)
    {
    }

    /**
     * Answers the domain of the target.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Converts the operand for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $from = $this->operand->domain();
        $context = $frame->context;

        return match ($this->domain->kind) {
            Kind::Integer => $this->integer($value, $from, $context),
            Kind::Decimal => $this->decimal((string) Convert::toDecimal($value, $from, $context), $context),
            Kind::Double => Convert::toDouble($value, $from, $context),
            Kind::Date, Kind::DateTime, Kind::Time => (new Moments())->convert($value, $from, $this->domain, $context),
            Kind::Year => (new Moments())->year($value, $from, $context),
            Kind::String, Kind::Json, Kind::Bit, Kind::Null => $this->text((string) Convert::toText($value, $from), $context),
        };
    }

    /**
     * Converts to SIGNED or UNSIGNED.
     */
    public function integer(int|float|string $value, Domain $from, Context $context): int
    {
        $result = (int) Convert::toInteger($value, $from, $context, $this->domain->unsigned);
        if ($this->domain->unsigned && $from->kind !== Kind::Double && !$from->unsigned && $result < 0 && Decimal::compare((string) Convert::toDecimal($value, $from, $context), '0') < 0) {
            $context->warning(ErrorCode::UnknownError, 'Cast to unsigned converted negative integer to its positive complement');
        }

        return $result;
    }

    /**
     * Converts to DECIMAL(M,D), clamping to the largest value of the precision with a warning.
     */
    public function decimal(string $value, Context $context): string
    {
        $rounded = Decimal::round($value, $this->domain->decimals);
        $digits = $this->domain->precision() - $this->domain->decimals;
        if (Decimal::integerDigits($rounded) > $digits && trim(explode('.', ltrim($rounded, '-'))[0], '0') !== '') {
            $context->warning(ErrorCode::DataOutOfRange, 'DECIMAL', '');
            $largest = str_repeat('9', max(1, $digits)) . ($this->domain->decimals > 0 ? '.' . str_repeat('9', $this->domain->decimals) : '');

            return str_starts_with($rounded, '-') ? '-' . $largest : $largest;
        }

        return $rounded;
    }

    /**
     * Converts to CHAR or BINARY, cutting to the length of the target.
     */
    public function text(string $value, Context $context): string
    {
        if ($this->limit === null) {
            return $value;
        }
        $characters = $this->domain->collation->charset;
        if ($characters->length($value) <= $this->limit) {
            return $value;
        }
        $context->warning(ErrorCode::TruncatedWrongValue, $this->target . '(' . $this->limit . ')', $value);

        return $characters->maxLength === 1 || !mb_check_encoding($value, 'UTF-8') ? substr($value, 0, $this->limit) : mb_substr($value, 0, $this->limit, 'UTF-8');
    }
}
