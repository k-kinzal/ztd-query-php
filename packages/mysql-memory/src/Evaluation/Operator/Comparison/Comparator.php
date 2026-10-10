<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Compile\Family\Jsons;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Order;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Compares two values of known domains as the server decides to compare them.
 *
 * Two strings compare as strings in their aggregated collation, converted into its character set; two integers as integers, a BIT
 * value as the unsigned integer of its bits; a
 * date or time and a string or another temporal value as datetimes; a decimal and a decimal or
 * integer as decimals; anything else as doubles. A JSON value and any value compare as JSON values,
 * the other value made a JSON value from its type: a string is a JSON string, not a JSON text, and
 * a predicate a JSON boolean ({@see JsonNode::compare()}).
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
     * @param bool $leftBoolean Whether the left value is that of a predicate, which a JSON comparison takes as a JSON boolean
     * @param bool $rightBoolean Whether the right value is that of a predicate
     */
    public function __construct(public readonly Kind $mode, public readonly Domain $left, public readonly Domain $right, public readonly Collation $collation, public readonly bool $leftBoolean = false, public readonly bool $rightBoolean = false)
    {
    }

    /**
     * Answers the same comparator, the values of predicates on either side taken as JSON booleans.
     */
    public function withBooleans(bool $left, bool $right): self
    {
        return new self($this->mode, $this->left, $this->right, $this->collation, $left, $right);
    }

    /**
     * Decides how two domains compare.
     *
     * @throws \MySqlMemory\Error\SqlError When two strings have collations that do not mix
     */
    public static function of(Domain $left, Domain $right, string $operation, Collation $connection, GrammarRelease $release = GrammarRelease::MySql847): self
    {
        $mode = self::mode($left, $right);
        $collation = $mode === Kind::String || $mode->temporal() ? Collations::aggregate([$left, $right], $operation, $connection, true, $release)[0] : Collation::binary();

        return new self($mode, $left, $right, $collation);
    }

    /**
     * Answers the kind two domains compare as.
     */
    public static function mode(Domain $left, Domain $right): Kind
    {
        return match (true) {
            $left->kind === Kind::Null && $right->kind === Kind::Null => Kind::String,
            self::textual($left) && self::textual($right) => Kind::String,
            $left->kind === Kind::Json || $right->kind === Kind::Json => Kind::Json,
            self::integers($left, $right) => Kind::Integer,
            $left->kind->temporal() && ($right->kind->temporal() || self::textual($right)), $right->kind->temporal() && self::textual($left) => self::temporal($left, $right),
            ($left->kind === Kind::Decimal || self::integral($left)) && ($right->kind === Kind::Decimal || self::integral($right)) => Kind::Decimal,
            default => Kind::Double,
        };
    }

    /**
     * Tells whether a domain compares as a string: a string, or NULL.
     */
    public static function textual(Domain $domain): bool
    {
        return $domain->kind === Kind::String || $domain->kind === Kind::Null;
    }

    /**
     * Tells whether a domain holds integers: an integer, a YEAR or a BIT value.
     */
    public static function integral(Domain $domain): bool
    {
        return $domain->kind === Kind::Integer || $domain->kind === Kind::Year || $domain->kind === Kind::Bit;
    }

    /**
     * Tells whether two domains compare as integers: both hold integers, or one does and the other is NULL.
     */
    public static function integers(Domain $left, Domain $right): bool
    {
        return (self::integral($left) && self::integral($right)) || (self::integral($left) && $right->kind === Kind::Null) || ($left->kind === Kind::Null && self::integral($right));
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
            Kind::Json => self::json($left, $this->left, $this->leftBoolean)->compare(self::json($right, $this->right, $this->rightBoolean)),
            Kind::String => Ordering::of($this->collation)->compare(self::text($left, $this->left, $this->collation), self::text($right, $this->right, $this->collation)),
            Kind::Integer => Integer::compare(self::integer($left, $this->left, $context), $this->left->unsigned || $this->left->kind === Kind::Bit, self::integer($right, $this->right, $context), $this->right->unsigned || $this->right->kind === Kind::Bit),
            Kind::Decimal => Decimal::compare((string) Convert::toDecimal($left, $this->left, $context), (string) Convert::toDecimal($right, $this->right, $context)),
            Kind::DateTime, Kind::Time, Kind::Date => $this->temporalOrder($left, $right, $context),
            Kind::Double, Kind::Year, Kind::Bit, Kind::Null => (float) Convert::toDouble($left, $this->left, $context) <=> (float) Convert::toDouble($right, $this->right, $context),
        };
    }

    /**
     * Answers a value compared as JSON: a JSON value as it is, any other value made a JSON value from its type, a string being a JSON string.
     *
     * @param bool $boolean Whether the value is that of a predicate
     */
    public static function json(int|float|string $value, Domain $domain, bool $boolean): JsonNode
    {
        return $domain->kind === Kind::Json ? JsonNode::load((string) $value) : Jsons::value($value, $domain, $boolean);
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
