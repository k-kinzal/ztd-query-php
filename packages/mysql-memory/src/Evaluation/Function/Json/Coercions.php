<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reads a JSON value as a number or a temporal value, as the server converts JSON values to other types.
 *
 * A number converts by its value: a double read as an integer is rounded half to even and a
 * decimal half away from zero, and one beyond the 64-bit range takes the nearest bound with
 * ER_NUMERIC_JSON_VALUE_OUT_OF_RANGE; an unsigned integer keeps its 64 bits. A boolean is 1 or 0.
 * A string is read as a number from its start, after leading whitespace; whatever follows the
 * number, trailing spaces included except for a decimal, is ignored with ER_INVALID_JSON_VALUE_FOR_CAST,
 * and an integer beyond 64 bits takes the nearest bound with ER_NUMERIC_JSON_VALUE_OUT_OF_RANGE.
 * Every other value (null, an array, an object, a temporal or an opaque value) is 0 with
 * ER_INVALID_JSON_VALUE_FOR_CAST. To a temporal type, a string is read as a date or time and a
 * temporal value is itself; every other value is NULL with ER_INVALID_JSON_VALUE_FOR_CAST. The
 * warnings name the JSON column or the function the value comes from and the row being sent
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html ("Converting between JSON and non-JSON values").
 *
 * @visibility MySqlMemory
 */
final class Coercions
{
    /**
     * Reads a JSON value as a double.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function toDouble(string $stored, Domain $domain, Context $context): float
    {
        $node = JsonNode::load($stored);

        return match ($node->type) {
            JsonKind::Integer, JsonKind::Unsigned, JsonKind::Decimal => (float) $node->scalar(),
            JsonKind::Double => is_float($node->value) ? $node->value : 0.0,
            JsonKind::Boolean => $node->value === true ? 1.0 : 0.0,
            JsonKind::String => (float) self::text($node->scalar(), '/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', false, 'DOUBLE', $domain, $context),
            JsonKind::Null, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => (float) self::invalid('DOUBLE', $domain, $context),
        };
    }

    /**
     * Reads a JSON value as a 64-bit integer, whose bits read as unsigned when the target is.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function toInteger(string $stored, Domain $domain, Context $context): int
    {
        $node = JsonNode::load($stored);

        return match ($node->type) {
            JsonKind::Integer, JsonKind::Unsigned => Integer::fromUnsignedText($node->scalar()),
            JsonKind::Double => self::round(is_float($node->value) ? $node->value : 0.0, $domain, $context),
            JsonKind::Decimal => self::bounded(Decimal::round($node->scalar(), 0), $domain, $context),
            JsonKind::Boolean => $node->value === true ? 1 : 0,
            JsonKind::String => self::bounded(self::text($node->scalar(), '/\A[+-]?[0-9]+/', false, 'INTEGER', $domain, $context), $domain, $context),
            JsonKind::Null, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => (int) self::invalid('INTEGER', $domain, $context),
        };
    }

    /**
     * Reads a JSON value as an exact decimal text.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function toDecimal(string $stored, Domain $domain, Context $context): string
    {
        $node = JsonNode::load($stored);

        return match ($node->type) {
            JsonKind::Integer, JsonKind::Unsigned => (string) Integer::fromUnsignedText($node->scalar()),
            JsonKind::Decimal => $node->scalar(),
            JsonKind::Double => Decimal::fromDouble(is_float($node->value) ? $node->value : 0.0),
            JsonKind::Boolean => $node->value === true ? '1' : '0',
            JsonKind::String => self::exact(self::text($node->scalar(), '/\A[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/', true, 'DECIMAL', $domain, $context)),
            JsonKind::Null, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => (string) self::invalid('DECIMAL', $domain, $context),
        };
    }

    /**
     * Reads a JSON value as a temporal value of a kind: the text and the domain of the value to convert, or null with a warning when it is no string and no temporal value the kind takes.
     *
     * A time converts to a time only, and a date, a datetime or a timestamp to a date or a datetime only.
     *
     * @return array{string, Domain}|null
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function toTemporal(string $stored, Domain $domain, Kind $target, Context $context): ?array
    {
        $node = JsonNode::load($stored);
        $time = $target === Kind::Time;

        $read = match (true) {
            $node->type === JsonKind::String => [$node->scalar(), Domain::string(strlen($node->scalar()), Collation::known('utf8mb4_bin'))],
            $node->type === JsonKind::Time && $time => [$node->scalar(), new Domain(Kind::Time, Field::Time, 17, 6)],
            $node->type === JsonKind::Date && !$time => [$node->scalar(), new Domain(Kind::Date, Field::Date, 10)],
            ($node->type === JsonKind::DateTime || $node->type === JsonKind::Timestamp) && !$time => [$node->scalar(), new Domain(Kind::DateTime, Field::DateTime, 26, 6)],
            default => null,
        };
        if ($read === null) {
            self::invalid('DATE/TIME/DATETIME/TIMESTAMP', $domain, $context, null);
        }

        return $read;
    }

    /**
     * Reads the number at the start of a string, warning when it is empty or more follows it.
     *
     * @param string $pattern The number the string starts with
     * @param bool $spaces Whether trailing spaces may follow the number without warning
     * @param string $target The type the warning names
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function text(string $text, string $pattern, bool $spaces, string $target, Domain $domain, Context $context): string
    {
        $trimmed = ltrim($text, " \t\n\r\v\f");
        $number = preg_match($pattern, $trimmed, $match) === 1 ? $match[0] : '';
        $rest = substr($trimmed, strlen($number));
        if ($number === '' || ($spaces ? rtrim($rest, ' ') : $rest) !== '') {
            self::invalid($target, $domain, $context);
        }

        return $number === '' ? '0' : $number;
    }

    /**
     * Writes a number read from a string as an exact decimal, its exponent applied.
     *
     * @example A number with an exponent
     *     \MySqlMemory\Evaluation\Function\Json\Coercions::exact('1.5e2') // => '150'
     */
    public static function exact(string $number): string
    {
        if (!str_contains(strtolower($number), 'e')) {
            return Decimal::canonical(rtrim($number, '.'));
        }
        [$mantissa, $exponent] = explode('e', strtolower($number));
        $shift = (int) $exponent;
        $mantissa = rtrim($mantissa, '.');
        $scale = max(0, Decimal::scale($mantissa) - $shift);
        $factor = bcpow('10', (string) abs($shift), 0);

        return Decimal::canonical($shift >= 0 ? bcmul(Decimal::numeric($mantissa), $factor, $scale) : bcdiv(Decimal::numeric($mantissa), $factor, $scale));
    }

    /**
     * Rounds a double half to even into a 64-bit integer, taking the nearest bound with a warning beyond the range.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function round(float $value, Domain $domain, Context $context): int
    {
        $rounded = round($value, 0, PHP_ROUND_HALF_EVEN);
        if ($rounded >= 9223372036854775808.0 || $rounded < -9223372036854775808.0) {
            $context->warnMessage(DataError::NumericJsonValueOutOfRange, DataError::NumericJsonValueOutOfRange->message('INTEGER', '', $domain->source, $context->row));

            return $rounded < 0 ? PHP_INT_MIN : PHP_INT_MAX;
        }

        return (int) $rounded;
    }

    /**
     * Answers the 64 bits of an integer text, taking the nearest bound with a warning beyond the signed and unsigned ranges.
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function bounded(string $number, Domain $domain, Context $context): int
    {
        $number = Decimal::numeric($number);
        if (bccomp($number, (string) PHP_INT_MIN, 0) < 0 || bccomp($number, Integer::UNSIGNED_MAX, 0) > 0) {
            $context->warnMessage(DataError::NumericJsonValueOutOfRange, DataError::NumericJsonValueOutOfRange->message('INTEGER', '', $domain->source, $context->row));

            return str_starts_with($number, '-') ? PHP_INT_MIN : -1;
        }

        return Integer::fromUnsignedText($number);
    }

    /**
     * Warns that a JSON value does not convert to a type, and answers what it converts to.
     *
     * @param string $target The type the warning names
     * @param int|null $value What the value converts to
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public static function invalid(string $target, Domain $domain, Context $context, ?int $value = 0): ?int
    {
        $context->warnMessage(DataError::InvalidJsonValueForCast, DataError::InvalidJsonValueForCast->message($target, '', $domain->source, $context->row));

        return $value;
    }
}
