<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Json;

use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\NumericText;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Converts a JSON value exactly to a type, as JSON_VALUE converts to its RETURNING type and JSON_TABLE to the type of a column.
 *
 * A value converts or does not: a string converts as its characters and any other value as its
 * JSON text, cut at nothing, so that a longer one, or one the character set cannot hold, does not
 * convert; a number converts by its value, a double rounded half to even and a decimal half away
 * from zero, within the range of the type; a string converts to a number only when it is wholly
 * one, an empty string being 0 for a double; dates and times convert from strings and temporal
 * values, a DATETIME only from a string with a time and a TIME only from one with a time or a
 * colon; a YEAR is 0, false, or 1901 to 2155; an unsigned integer beyond the signed range is a
 * negative decimal; a JSON column takes any value (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#function_json-value,
 * https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Returning
{
    /**
     * @param Domain $domain The type converted to
     * @param bool $storing Whether the value is stored into a column of JSON_TABLE, which reads numbers as times and years, dates as datetimes, and drops trailing spaces that do not fit
     */
    public function __construct(public readonly Domain $domain, public readonly bool $storing = false)
    {
    }

    /**
     * Answers the name of the type the out-of-range error of JSON_VALUE gives.
     */
    public function name(): string
    {
        return match ($this->domain->kind) {
            Kind::Integer => $this->domain->unsigned ? 'UNSIGNED' : 'SIGNED',
            Kind::Decimal => 'DECIMAL',
            Kind::Double => $this->domain->field === Field::Float ? 'FLOAT' : 'DOUBLE',
            Kind::Date => 'DATE',
            Kind::Time => 'TIME',
            Kind::DateTime => 'DATETIME',
            Kind::Year => 'YEAR',
            Kind::String, Kind::Json, Kind::Bit, Kind::Null => 'STRING',
        };
    }

    /**
     * Converts a value to the type: whether it converts exactly, and the converted value.
     *
     * @param bool $containers Whether an array or an object converts to a type that is not JSON, as its JSON text
     *
     * @return array{bool, int|float|string|null}
     */
    public function coerce(JsonNode $node, bool $containers): array
    {
        if (!$containers && $this->domain->kind !== Kind::Json && ($node->type === JsonKind::Array || $node->type === JsonKind::Object)) {
            return [false, null];
        }

        return match ($this->domain->kind) {
            Kind::Json => [true, $node->store()],
            Kind::String => $this->text($node->unquoted()),
            Kind::Integer => $this->integer($node),
            Kind::Decimal => $this->decimal($node),
            Kind::Double => $this->double($node),
            Kind::Date, Kind::DateTime, Kind::Time => $this->moment($node),
            Kind::Year => $this->year($node),
            Kind::Bit, Kind::Null => [false, null],
        };
    }

    /**
     * Converts the text of a value to the string type: whole, in its character set.
     *
     * @return array{bool, string|null}
     */
    public function text(string $text): array
    {
        $charset = $this->domain->collation->charset;
        $utf8 = Charset::known('utf8mb4');
        if ($charset !== Charset::binary() && Encoding::convertible($text, $utf8, $charset) !== strlen($text)) {
            return [false, null];
        }
        $converted = $charset === Charset::binary() ? $text : Encoding::convert($text, $utf8, $charset);
        $limited = in_array($this->domain->field, [Field::LongBlob, Field::MediumBlob, Field::Blob, Field::TinyBlob], true);
        $length = $charset === Charset::binary() ? strlen($converted) : mb_strlen($text, 'UTF-8');

        if ($this->storing && $charset === Charset::binary() && $this->domain->field === Field::String && $length < $this->domain->length) {
            $converted = str_pad($converted, $this->domain->length, "\0");
        }
        if (!$limited && $length > $this->domain->length && $this->storing && $charset !== Charset::binary() && rtrim($text, ' ') !== $text) {
            return $this->text(mb_substr($text, 0, max($this->domain->length, mb_strlen(rtrim($text, ' '), 'UTF-8')), 'UTF-8'));
        }

        return !$limited && $length > $this->domain->length ? [false, null] : [true, $converted];
    }

    /**
     * Converts a value to SIGNED or UNSIGNED within its range.
     *
     * @return array{bool, int|null}
     */
    public function integer(JsonNode $node): array
    {
        $number = match ($node->type) {
            JsonKind::Integer, JsonKind::Unsigned => $node->scalar(),
            JsonKind::Double => is_float($node->value) && is_finite($node->value) ? Decimal::fromDouble(round($node->value, 0, PHP_ROUND_HALF_EVEN)) : null,
            JsonKind::Decimal => Decimal::round($node->scalar(), 0),
            JsonKind::Boolean => $node->value === true ? '1' : '0',
            JsonKind::String => preg_match('/\A[ \t\n\r]*([+-]?[0-9]+)\z/', $node->scalar(), $match) === 1 ? $match[1] : null,
            JsonKind::Null, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => null,
        };
        if ($number === null) {
            return [false, null];
        }
        $number = Decimal::numeric($number);
        $bits = [Field::Tiny->value => 8, Field::Short->value => 16, Field::Int24->value => 24, Field::Long->value => 32][$this->domain->field->value] ?? 64;
        $fits = $bits === 64 ? ($this->domain->unsigned ? Integer::unsignedRange($number) : Integer::signedRange($number)) : $this->within($number, $bits);

        return $fits ? [true, Integer::fromUnsignedText($number)] : [false, null];
    }

    /**
     * Converts a value to DECIMAL(M,D), rounded to its scale, within its precision.
     *
     * @return array{bool, string|null}
     */
    public function decimal(JsonNode $node): array
    {
        $number = match ($node->type) {
            JsonKind::Integer, JsonKind::Decimal => $node->scalar(),
            JsonKind::Unsigned => (string) Integer::fromUnsignedText($node->scalar()),
            JsonKind::Double => is_float($node->value) && is_finite($node->value) ? Decimal::fromDouble($node->value) : null,
            JsonKind::Boolean => $node->value === true ? '1' : '0',
            JsonKind::String => self::numeric($node->scalar()),
            JsonKind::Null, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => null,
        };
        if ($number === null) {
            return [false, null];
        }
        $rounded = Decimal::round($number, $this->domain->decimals);
        if (Decimal::integerDigits($rounded) > $this->domain->precision() - $this->domain->decimals && trim(explode('.', ltrim($rounded, '-'))[0], '0') !== '') {
            return [false, null];
        }

        return [true, $rounded];
    }

    /**
     * Converts a value to DOUBLE or FLOAT; one beyond the range of a FLOAT is 0 (verified on a live 8.4 server).
     *
     * @return array{bool, float|null}
     */
    public function double(JsonNode $node): array
    {
        $number = match ($node->type) {
            JsonKind::Integer, JsonKind::Unsigned, JsonKind::Decimal => $node->scalar(),
            JsonKind::Double => is_float($node->value) ? Decimal::fromDouble($node->value) : null,
            JsonKind::Boolean => $node->value === true ? '1' : '0',
            JsonKind::String => trim($node->scalar(), " \t\n\r") === '' ? '0' : self::numeric($node->scalar()),
            JsonKind::Null, JsonKind::Array, JsonKind::Object, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp, JsonKind::Opaque => null,
        };
        $value = $node->type === JsonKind::Double && is_float($node->value) ? $node->value : ($number === null ? null : (float) $number);
        if ($value === null) {
            return [false, null];
        }

        if ($this->domain->field === Field::Float && abs($value) > 3.4028234663852886e38) {
            return $this->storing ? [false, null] : [true, 0.0];
        }

        return [true, $value];
    }

    /**
     * Answers the number a string wholly holds, or null when it holds more or less than a number.
     *
     * @example A number with spaces around it
     *     \MySqlMemory\Evaluation\Function\Json\Returning::numeric(' 1.5 ') // => '1.5'
     */
    public static function numeric(string $text): ?string
    {
        $read = NumericText::exact($text);

        return $read->complete && trim($text, " \t\n\r") !== '' ? $read->number : null;
    }

    /**
     * Converts a string, a temporal value or a timestamp to DATE, TIME or DATETIME.
     *
     * @return array{bool, string|null}
     */
    public function moment(JsonNode $node): array
    {
        if ($this->storing && $this->domain->kind === Kind::Time && ($node->type->numeric() || ($node->type === JsonKind::String && !str_contains($node->scalar(), ':')))) {
            return $this->duration($node);
        }
        if (!in_array($node->type, [JsonKind::String, JsonKind::Date, JsonKind::Time, JsonKind::DateTime, JsonKind::Timestamp], true)) {
            return [false, null];
        }

        return $this->domain->kind === Kind::Time ? $this->time($node) : $this->calendar($node);
    }

    /**
     * Converts a number, or a string without a colon, stored into a TIME column: the number read as a time of hours, minutes and seconds, rounded to whole seconds.
     *
     * @return array{bool, string|null}
     */
    public function duration(JsonNode $node): array
    {
        if ($node->type === JsonKind::String && preg_match('/\A[ \t\n\r]*[+-]?\.?[0-9]/', $node->scalar()) !== 1) {
            return [false, null];
        }
        $number = $node->type === JsonKind::String ? NumericText::real($node->scalar())->number : JsonNode::number($node);
        $time = Temporal::parseTime(Decimal::round(Decimal::numeric(Decimal::canonical($number === '' ? '0' : $number)), 0));

        return $time === null || $time[1] > 838 ? [false, null] : [true, Temporal::time($time[0], $time[1], $time[2], $time[3], 0, $this->domain->decimals)];
    }

    /**
     * Converts a string or a temporal value to TIME: the time of a string with a valid date and a time, else a string with a colon or a TIME value read as a time.
     *
     * @return array{bool, string|null}
     */
    public function time(JsonNode $node): array
    {
        $text = $node->scalar();
        $decimals = $this->domain->decimals;
        $moment = $node->type === JsonKind::String ? Temporal::parseDateTime($text) : null;
        if ($moment !== null && $moment[7] && Temporal::valid($moment[0], $moment[1], $moment[2])) {
            return [true, Temporal::time(false, $moment[3], $moment[4], $moment[5], Temporal::scale($moment[6], $decimals, false), $decimals)];
        }
        $time = ($node->type === JsonKind::String && str_contains($text, ':')) || $node->type === JsonKind::Time ? Temporal::parseTime($text) : null;

        return $time === null ? [false, null] : [true, Temporal::time($time[0], $time[1], $time[2], $time[3], Temporal::scale($time[4], $decimals, false), $decimals)];
    }

    /**
     * Converts a string or a temporal value other than a TIME value to DATE or DATETIME; a DATETIME only from a string with a time, unless the value is stored into a column.
     *
     * @return array{bool, string|null}
     */
    public function calendar(JsonNode $node): array
    {
        $parts = $node->type === JsonKind::Time ? null : Temporal::parseDateTime($node->scalar());
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            return [false, null];
        }
        if ($this->domain->kind === Kind::DateTime && $node->type === JsonKind::String && !$parts[7] && !$this->storing) {
            return [false, null];
        }
        if ($this->domain->kind === Kind::Date) {
            return [true, Temporal::date($parts[0], $parts[1], $parts[2])];
        }
        $decimals = $this->domain->decimals;

        return [true, Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], Temporal::scale($parts[6], $decimals, false), $decimals)];
    }

    /**
     * Converts an integer or false to YEAR: 0, or 1901 to 2155; stored into a column, any whole number, 1 to 99 read as 2001 to 2069 and 1970 to 1999.
     *
     * @return array{bool, int|null}
     */
    public function year(JsonNode $node): array
    {
        if ($this->storing) {
            [$read, $number] = $this->integer($node);
            $year = (int) $number;

            return match (true) {
                !$read => [false, null],
                $year === 0 || ($year >= 1901 && $year <= 2155) => [true, $year],
                $year >= 1 && $year <= 69 => [true, 2000 + $year],
                $year >= 70 && $year <= 99 => [true, 1900 + $year],
                default => [false, null],
            };
        }
        if ($node->type === JsonKind::Boolean) {
            return $node->value === false ? [true, 0] : [false, null];
        }
        if ($node->type !== JsonKind::Integer && $node->type !== JsonKind::Unsigned) {
            return [false, null];
        }
        $year = (int) $node->scalar();

        return $year === 0 || ($year >= 1901 && $year <= 2155) ? [true, $year] : [false, null];
    }

    /**
     * Tells whether an integer fits an integer type of a number of bits, signed or unsigned as the type is.
     *
     * @example 300 does not fit a TINYINT
     *     (new \MySqlMemory\Evaluation\Function\Json\Returning(\MySqlMemory\Typing\Domain::integer(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Tiny, 4)))->within('300', 8) // => false
     */
    public function within(string $number, int $bits): bool
    {
        $lowest = $this->domain->unsigned ? '0' : '-' . bcpow('2', (string) ($bits - 1), 0);
        $highest = $this->domain->unsigned ? bcsub(bcpow('2', (string) $bits, 0), '1', 0) : bcsub(bcpow('2', (string) ($bits - 1), 0), '1', 0);

        $number = Decimal::numeric($number);

        return bccomp($number, Decimal::numeric($lowest), 0) >= 0 && bccomp($number, Decimal::numeric($highest), 0) <= 0;
    }
}
