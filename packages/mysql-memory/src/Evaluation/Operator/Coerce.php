<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Converts a value of one domain to the run-time kind of another, for results that aggregate several domains.
 *
 * A string converts into the character set of the result.
 *
 * @visibility MySqlMemory
 */
final class Coerce
{
    /**
     * Converts a value to the kind of a target domain.
     */
    public static function to(int|float|string|null $value, Domain $from, Domain $to, Context $context): int|float|string|null
    {
        if ($value === null || $from->kind === $to->kind && ($to->kind !== Kind::Decimal || $from->decimals === $to->decimals) && ($to->kind !== Kind::String || $from->collation->charset === $to->collation->charset)) {
            return $value;
        }

        return match ($to->kind) {
            Kind::Integer => Convert::toInteger($value, $from, $context, $to->unsigned),
            Kind::Decimal => Decimal::round((string) Convert::toDecimal($value, $from, $context), $to->decimals),
            Kind::Double => Convert::toDouble($value, $from, $context),
            Kind::String => Encoding::convert((string) Convert::toText($value, $from), $from->kind === Kind::String ? $from->collation->charset : Charset::known('utf8mb4'), $to->collation->charset),
            Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Convert::toText($value, $from),
        };
    }

    /**
     * Converts the branch IF or CASE chose to their result: a decimal keeps the scale of the branch, as the server returns it.
     */
    public static function branch(int|float|string|null $value, Domain $from, Domain $to, Context $context): int|float|string|null
    {
        return $to->kind === Kind::Decimal ? Convert::toDecimal($value, $from, $context) : self::to($value, $from, $to, $context);
    }

}
