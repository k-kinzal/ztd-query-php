<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\NumericText;
use MySqlMemory\Value\Real;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reads a value of one domain as a value of another kind, as the server reads an operand in a context.
 *
 * A string read as a number takes the number at its start and warns (ER_TRUNCATED_WRONG_VALUE)
 * when more follows. A double read as an integer is rounded half to even and saturates; a
 * decimal is rounded half away from zero.
 *
 * @visibility MySqlMemory
 */
final class Convert
{
    /**
     * Reads a value as a double.
     */
    public static function toDouble(int|float|string|null $value, Domain $domain, Context $context): ?float
    {
        if ($value === null) {
            return null;
        }

        if ($domain->numericBytes && $domain->kind === Kind::String) {
            $domain = new Domain(Kind::Bit, $domain->field, $domain->length, 0, true);
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => Integer::real((int) $value, $domain->unsigned),
            Kind::Double => (float) $value,
            Kind::Decimal => (float) $value,
            Kind::Date, Kind::Time, Kind::DateTime => (float) Temporal::number((string) $value),
            Kind::Bit => Integer::real(self::bits((string) $value), true),
            Kind::String, Kind::Json => (float) self::stringNumber(self::readable((string) $value, $domain), 'DOUBLE', $context, false, self::readableCharset($domain)),
            Kind::Null => null,
        };
    }

    /**
     * Reads a value as a 64-bit integer; the result is unsigned when the target asks for it.
     */
    public static function toInteger(int|float|string|null $value, Domain $domain, Context $context, bool $unsigned = false): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($domain->numericBytes && $domain->kind === Kind::String) {
            $domain = new Domain(Kind::Bit, $domain->field, $domain->length, 0, true);
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => (int) $value,
            Kind::Double => Integer::fromReal((float) $value, $unsigned),
            Kind::Decimal => self::exactInteger(Decimal::round((string) $value, 0), $unsigned),
            Kind::Date, Kind::Time, Kind::DateTime => self::exactInteger(Decimal::round(Temporal::number((string) $value), 0), $unsigned),
            Kind::Bit => self::bits((string) $value),
            Kind::String, Kind::Json => self::stringInteger(self::readable((string) $value, $domain), $context, $unsigned, self::readableCharset($domain)),
            Kind::Null => null,
        };
    }

    /**
     * Reads a value as an exact decimal text.
     */
    public static function toDecimal(int|float|string|null $value, Domain $domain, Context $context): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($domain->numericBytes && $domain->kind === Kind::String) {
            $domain = new Domain(Kind::Bit, $domain->field, $domain->length, 0, true);
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => Integer::text((int) $value, $domain->unsigned),
            Kind::Double => Decimal::fromDouble((float) $value),
            Kind::Decimal => (string) $value,
            Kind::Date, Kind::Time, Kind::DateTime => Temporal::number((string) $value),
            Kind::Bit => Integer::text(self::bits((string) $value), true),
            Kind::String, Kind::Json => self::stringNumber(self::readable((string) $value, $domain), 'DECIMAL', $context, true, self::readableCharset($domain)),
            Kind::Null => null,
        };
    }

    /**
     * Reads a value as the text the server writes for it.
     */
    public static function toText(int|float|string|null $value, Domain $domain): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year => Integer::text((int) $value, $domain->unsigned),
            Kind::Double => $domain->decimals < Domain::NOT_FIXED ? Real::fixed((float) $value, $domain->decimals) : Real::format((float) $value),
            Kind::Decimal, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Json, Kind::Bit, Kind::Null => (string) $value,
        };
    }

    /**
     * Reads a value as a truth value: whether it is a number other than zero; null stays null.
     */
    public static function toBool(int|float|string|null $value, Domain $domain, Context $context): ?bool
    {
        if ($value === null) {
            return null;
        }

        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => $value !== 0 && $value !== "\0",
            Kind::Double => (float) $value !== 0.0,
            Kind::Decimal => Decimal::compare((string) $value, '0') !== 0,
            Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Json, Kind::Null => self::toDouble($value, $domain, $context) !== 0.0,
        };
    }

    /**
     * Reads the number at the start of a string as a decimal text and warns when more follows.
     *
     * @param Charset|null $charset The character set of the string, which the warning quotes it from
     */
    public static function stringNumber(string $text, string $kind, Context $context, bool $exact, ?Charset $charset = null): string
    {
        $read = $exact ? NumericText::exact($text) : NumericText::real($text);
        if (!$read->complete) {
            $context->warning(ErrorCode::TruncatedWrongValue, $kind, self::shown($text, $charset));
        }

        return $read->number;
    }

    /**
     * Answers the text of a string a number is read from: one of UCS-2, UTF-16 or UTF-32 is read in UTF-8.
     */
    public static function readable(string $text, Domain $domain): string
    {
        $charset = $domain->collation->charset;

        return $charset === self::readableCharset($domain) ? $text : Encoding::convert($text, $charset, Charset::known('utf8mb4'));
    }

    /**
     * Answers the character set of the text a number is read from: utf8mb4 for a string of UCS-2, UTF-16 or UTF-32, else the set of the domain.
     */
    public static function readableCharset(Domain $domain): Charset
    {
        $charset = $domain->collation->charset;

        return $domain->kind === Kind::String && in_array($charset->name, ['ucs2', 'utf16', 'utf16le', 'utf32'], true) ? Charset::known('utf8mb4') : $charset;
    }

    /**
     * Writes a string as a warning quotes it: a binary string with each byte outside printable ASCII as `\xHH`, another in UTF-8.
     */
    public static function shown(string $text, ?Charset $charset): string
    {
        if ($charset === Charset::binary()) {
            return (string) preg_replace_callback('/[^\x20-\x7E]/', static fn (array $byte): string => sprintf('\\x%02X', ord($byte[0])), $text);
        }

        return $charset === null ? $text : Encoding::convert($text, $charset, Charset::known('utf8mb4'));
    }

    /**
     * Reads the integer at the start of a string and warns when more follows or it overflows.
     *
     * @param Charset|null $charset The character set of the string, which the warning quotes it from
     */
    public static function stringInteger(string $text, Context $context, bool $unsigned, ?Charset $charset = null): int
    {
        $read = NumericText::integer($text);
        $number = Decimal::numeric($read->number);
        $inRange = $unsigned ? Integer::unsignedRange($number) || Integer::signedRange($number) : Integer::signedRange($number);
        if (!$read->complete || !$inRange) {
            $context->warning(ErrorCode::TruncatedWrongValue, 'INTEGER', self::shown($text, $charset));
        }
        if (!$inRange) {
            return str_starts_with($number, '-') ? PHP_INT_MIN : ($unsigned || bccomp($number, Integer::UNSIGNED_MAX, 0) >= 0 ? -1 : PHP_INT_MAX);
        }

        return $unsigned && bccomp($number, (string) PHP_INT_MAX, 0) > 0 ? Integer::fromUnsignedText($number) : (int) $number;
    }

    /**
     * Answers the int of an integer text, saturating at the bounds of the signed or unsigned range.
     */
    public static function exactInteger(string $number, bool $unsigned): int
    {
        $numeric = Decimal::numeric($number);
        if ($unsigned) {
            if (bccomp($numeric, '0', 0) < 0) {
                return 0;
            }

            return bccomp($numeric, Integer::UNSIGNED_MAX, 0) > 0 ? -1 : Integer::fromUnsignedText($numeric);
        }
        if (bccomp($numeric, (string) PHP_INT_MAX, 0) > 0) {
            return PHP_INT_MAX;
        }

        return bccomp($numeric, (string) PHP_INT_MIN, 0) < 0 ? PHP_INT_MIN : (int) $numeric;
    }

    /**
     * Answers the unsigned integer of the bytes of a BIT value, the first byte the most significant.
     */
    public static function bits(string $bytes): int
    {
        $value = 0;
        foreach (str_split(substr($bytes, -8)) as $byte) {
            $value = ($value << 8) | ord($byte);
        }

        return $value;
    }
}
