<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use MySqlMemory\Value\Decimal;

/**
 * Converts a value of one domain to the run-time kind of another, for results that aggregate several domains.
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
        if ($value === null || $from->kind === $to->kind && ($to->kind !== Kind::Decimal || $from->decimals === $to->decimals)) {
            return $value;
        }

        return match ($to->kind) {
            Kind::Integer => Convert::toInteger($value, $from, $context, $to->unsigned),
            Kind::Decimal => Decimal::round((string) Convert::toDecimal($value, $from, $context), $to->decimals),
            Kind::Double => Convert::toDouble($value, $from, $context),
            default => Convert::toText($value, $from),
        };
    }
}
