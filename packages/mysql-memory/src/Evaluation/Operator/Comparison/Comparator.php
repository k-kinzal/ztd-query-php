<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Order;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Compares two values of known domains as the server decides to compare them.
 *
 * Two strings compare as strings in their aggregated collation, converted into its character set; two integers as integers, a BIT
 * value as the unsigned integer of its bits; a
 * date or time and a string or another temporal value as datetimes; a decimal and a decimal or
 * integer as decimals; anything else as doubles.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 *
 * @visibility MySqlMemory
 */
final class Comparator
{
    /**
     * @param Kind $mode The kind both values are compared as
     * @param Domain $left The domain of the left value
     * @param Domain $right The domain of the right value
     * @param Collation $collation The collation strings are compared in
     */
    public function __construct(public readonly Kind $mode, public readonly Domain $left, public readonly Domain $right, public readonly Collation $collation)
    {
    }

    /**
     * Decides how two domains compare.
     *
     * @throws \MySqlMemory\Error\SqlError When two strings have collations that do not mix
     */
    public static function of(Domain $left, Domain $right, string $operation, Collation $connection): self
    {
        $string = static fn (Domain $domain): bool => $domain->kind === Kind::String || $domain->kind === Kind::Null;
        $integer = static fn (Domain $domain): bool => $domain->kind === Kind::Integer || $domain->kind === Kind::Year || $domain->kind === Kind::Bit;
        $mode = match (true) {
            $left->kind === Kind::Null && $right->kind === Kind::Null => Kind::String,
            $string($left) && $string($right) => Kind::String,
            $left->kind === Kind::Json || $right->kind === Kind::Json => Kind::Json,
            $integer($left) && $integer($right), $integer($left) && $right->kind === Kind::Null, $left->kind === Kind::Null && $integer($right) => Kind::Integer,
            $left->kind->temporal() && ($right->kind->temporal() || $string($right)), $right->kind->temporal() && $string($left) => self::temporal($left, $right),
            ($left->kind === Kind::Decimal || $integer($left)) && ($right->kind === Kind::Decimal || $integer($right)) => Kind::Decimal,
            default => Kind::Double,
        };
        $collation = $mode === Kind::String || $mode->temporal() ? Collations::aggregate([$left, $right], $operation, $connection, true)[0] : Collation::binary();

        return new self($mode, $left, $right, $collation);
    }

    /**
     * Answers the temporal kind two values compare as: TIME when both are times, else DATETIME.
     */
    public static function temporal(Domain $left, Domain $right): Kind
    {
        if ($left->kind === Kind::Time && ($right->kind === Kind::Time || !$right->kind->temporal())) {
            return Kind::Time;
        }
        if ($right->kind === Kind::Time && !$left->kind->temporal()) {
            return Kind::Time;
        }

        return Kind::DateTime;
    }

    /**
     * Compares two values: negative, zero or positive; null when either is NULL.
     */
    public function compare(int|float|string|null $left, int|float|string|null $right, Context $context): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }

        return match ($this->mode) {
            Kind::String, Kind::Json => Ordering::of($this->collation)->compare(self::text($left, $this->left, $this->collation), self::text($right, $this->right, $this->collation)),
            Kind::Integer => Integer::compare(self::integer($left, $this->left, $context), $this->left->unsigned || $this->left->kind === Kind::Bit, self::integer($right, $this->right, $context), $this->right->unsigned || $this->right->kind === Kind::Bit),
            Kind::Decimal => Decimal::compare((string) Convert::toDecimal($left, $this->left, $context), (string) Convert::toDecimal($right, $this->right, $context)),
            Kind::DateTime, Kind::Time, Kind::Date => $this->temporalOrder($left, $right, $context),
            Kind::Double, Kind::Year, Kind::Bit, Kind::Null => (float) Convert::toDouble($left, $this->left, $context) <=> (float) Convert::toDouble($right, $this->right, $context),
        };
    }

    /**
     * Answers the 64 bits of a value compared as an integer: a BIT value is held as its bytes.
     */
    public static function integer(int|float|string $value, Domain $domain, Context $context): int
    {
        return $domain->kind === Kind::Bit ? (int) Convert::toInteger($value, $domain, $context, true) : (int) $value;
    }

    /**
     * Compares two values as datetimes or times.
     */
    public function temporalOrder(int|float|string $left, int|float|string $right, Context $context): int
    {
        $leftValue = self::moment($left, $this->left, $this->mode);
        $rightValue = self::moment($right, $this->right, $this->mode);
        if ($leftValue === null || $rightValue === null) {
            return Ordering::of($this->collation)->compare(self::text($left, $this->left, $this->collation), self::text($right, $this->right, $this->collation));
        }

        return $this->mode === Kind::Time ? Order::time($leftValue) <=> Order::time($rightValue) : $leftValue <=> $rightValue;
    }

    /**
     * Writes a value as a comparable datetime or time text, or answers null when it is none.
     */
    public static function moment(int|float|string $value, Domain $domain, Kind $mode): ?string
    {
        if ($mode === Kind::Time) {
            $parts = Temporal::parseTime((string) $value);

            return $parts === null ? null : Temporal::time($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], 6);
        }
        if ($domain->kind === Kind::Time) {
            return null;
        }
        $parts = Temporal::parseDateTime((string) $value);

        return $parts === null ? null : Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], 6);
    }

    /**
     * Answers the text of a value compared as a string, in the character set of the collation it is compared in.
     */
    public static function text(int|float|string $value, Domain $domain, Collation $collation): string
    {
        return Encoding::convert((string) Convert::toText($value, $domain), $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4'), $collation->charset);
    }
}
