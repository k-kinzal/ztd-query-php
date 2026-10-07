<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use JsonException;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Stores values into date, time, datetime, timestamp, year and JSON columns.
 *
 * A value that is no valid date or time is refused (ER_TRUNCATED_WRONG_VALUE) under a strict
 * mode and stored as the zero value otherwise; a time part dropped by a DATE column is only a
 * note. Fractional seconds beyond the precision of the column are rounded.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-types.html.
 *
 * @visibility MySqlMemory
 */
final class Times
{
    /**
     * @param Store $store The store writing the row
     */
    public function __construct(public readonly Store $store)
    {
    }

    /**
     * Stores into a temporal column.
     *
     * @throws SqlError When the value is refused
     */
    public function value(int|float|string $value, Domain $from, ColumnDefinition $column): int|string
    {
        $to = $column->domain;
        if ($to->kind === Kind::Year) {
            return $this->year($value, $from, $column);
        }
        $text = $from->kind === Kind::String || $from->kind->temporal() ? (string) $value : (string) Convert::toDecimal($value, $from, $this->store->context);
        if ($to->kind === Kind::Time) {
            return $this->time($text, $from, $column);
        }
        if ($from->kind === Kind::Time) {
            $date = date('Y-m-d', (int) $this->store->context->started);
            $text = $date . ' ' . ltrim($text, '-');
        }
        $parts = Temporal::parseDateTime($text);
        if ($parts === null || !Temporal::valid($parts[0], $parts[1], $parts[2]) || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            return $this->invalid($text, $column);
        }
        [$parts, $carry] = [$parts, $this->round($parts[6], $to->decimals)];
        if ($to->kind === Kind::Date) {
            if ($parts[7] && ($parts[3] !== 0 || $parts[4] !== 0 || $parts[5] !== 0 || $parts[6] !== 0)) {
                $this->store->context->note(ErrorCode::TruncatedWrongValueForField, 'date', $text, $column->name, $this->store->row);
            }

            return Temporal::date($parts[0], $parts[1], $parts[2]);
        }
        $moment = Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], 0, 0);
        if ($carry >= 1000000) {
            $moment = date('Y-m-d H:i:s', (int) strtotime($moment . ' +1 second'));
            $carry -= 1000000;
        }

        return $moment . Temporal::fraction($carry, $to->decimals);
    }

    /**
     * Rounds microseconds to a number of decimals; the result may reach one second.
     */
    public function round(int $micro, int $decimals): int
    {
        $unit = 10 ** (6 - $decimals);

        return (int) (round($micro / $unit) * $unit);
    }

    /**
     * Stores into a TIME column.
     *
     * @throws SqlError When the value is refused
     */
    public function time(string $text, Domain $from, ColumnDefinition $column): string
    {
        $decimals = $column->domain->decimals;
        if ($from->kind === Kind::Date || $from->kind === Kind::DateTime) {
            $parts = Temporal::parseDateTime($text);

            return $parts === null ? '00:00:00' : Temporal::time(false, $parts[3], $parts[4], $parts[5], $this->round($parts[6], $decimals), $decimals);
        }
        $parts = Temporal::parseTime($text);
        if ($parts === null || $parts[2] > 59 || $parts[3] > 59) {
            return $this->invalid($text, $column);
        }
        if ($parts[1] > 838) {
            $this->store->adjust(ErrorCode::TruncatedWrongValueForField, 'time', $text, $column->name, $this->store->row);

            return Temporal::time($parts[0], 838, 59, 59, 0, $decimals);
        }

        return Temporal::time($parts[0], $parts[1], $parts[2], $parts[3], $this->round($parts[4], $decimals), $decimals);
    }

    /**
     * Refuses or zeroes a value that is no valid date or time.
     *
     * @throws SqlError When the value is refused
     */
    public function invalid(string $text, ColumnDefinition $column): string
    {
        $kind = match ($column->domain->field) {
            Field::Date => 'date',
            Field::Time => 'time',
            Field::Decimal, Field::Tiny, Field::Short, Field::Long, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::LongLong, Field::Int24, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => 'datetime',
        };
        $this->store->adjust(ErrorCode::TruncatedWrongValueForField, $kind, $text, $column->name, $this->store->row);

        return match ($column->domain->kind) {
            Kind::Date => '0000-00-00',
            Kind::Time => Temporal::time(false, 0, 0, 0, 0, $column->domain->decimals),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => '0000-00-00 00:00:00' . Temporal::fraction(0, $column->domain->decimals),
        };
    }

    /**
     * Stores into a YEAR column.
     *
     * @throws SqlError When the value is refused
     */
    public function year(int|float|string $value, Domain $from, ColumnDefinition $column): int
    {
        $year = (int) Convert::toInteger($value, $from, $this->store->context);
        $text = $from->kind === Kind::String ? trim((string) $value) : '';
        if ($from->kind !== Kind::String || strlen($text) <= 2) {
            if ($year >= 1 && $year <= 69 || ($text !== '' && $year === 0 && $text !== '0')) {
                $year += 2000;
            } elseif ($year >= 70 && $year <= 99) {
                $year += 1900;
            }
        }
        if ($year !== 0 && ($year < 1901 || $year > 2155)) {
            $this->store->adjust(ErrorCode::OutOfRange, $column->name, $this->store->row);

            return 0;
        }

        return $year;
    }

    /**
     * Stores into a JSON column: the canonical text of a valid document.
     *
     * @throws SqlError When the text is no valid document
     */
    public function json(int|float|string $value, Domain $from, ColumnDefinition $column): string
    {
        $text = (string) Convert::toText($value, $from);
        if ($from->kind !== Kind::String && $from->kind !== Kind::Json) {
            return $text;
        }
        try {
            $document = json_decode($text, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $failure) {
            throw new SqlError(ErrorCode::InvalidJsonText, ErrorCode::InvalidJsonText->message('Invalid value.', 0, $column->name), $failure);
        }

        return (string) json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
