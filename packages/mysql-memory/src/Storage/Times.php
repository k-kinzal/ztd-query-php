<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Json\Json;
use MySqlMemory\Value\Json\JsonSyntax;
use MySqlMemory\Value\NumericText;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Stores values into date, time, datetime, timestamp, year and JSON columns.
 *
 * A value that is no date or time is stored as the zero value with WARN_DATA_TRUNCATED; a date
 * that does not exist, a zero date or a zero in a date the sql_mode forbids, a time beyond
 * 838:59:59 and a timestamp outside 1970-01-01 00:00:01 to 2038-01-19 03:14:07 UTC with
 * ER_WARN_DATA_OUT_OF_RANGE, a time clamped to its range. A value followed by more text keeps
 * what was read with WARN_DATA_TRUNCATED. Under a strict mode each of them is an error
 * (ER_TRUNCATED_WRONG_VALUE) naming the value and the column. Dropping a date or a time part is
 * only a note. Fractional seconds beyond the precision of the column are rounded half up, first
 * to six digits, and the carry reaches the date; under TIME_TRUNCATE_FRACTIONAL they are cut.
 * A year of one or two digits is in 2000-2069 or 1970-1999; a string zero of other than four
 * characters is 2000.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-types.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html,
 * https://dev.mysql.com/doc/refman/8.4/en/year.html,
 * https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html.
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

        return $this->moment($text, $from, $column);
    }

    /**
     * Stores into a DATE, DATETIME or TIMESTAMP column.
     *
     * @throws SqlError When the value is refused
     */
    public function moment(string $text, Domain $from, ColumnDefinition $column): string
    {
        $to = $column->domain;
        $context = $this->store->context;
        $truncate = $context->modes->has('TIME_TRUNCATE_FRACTIONAL');
        $numeric = !$from->kind->temporal() && $from->kind !== Kind::String;
        if ($from->kind === Kind::Time) {
            $time = Temporal::scanTime($text);
            $moment = $time === null ? null : Temporal::onDay($context->started, $time[0], $time[1], $time[2], $time[3], Temporal::micro($time[4], $truncate));
            $parts = $moment === null ? null : [$moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], '', true, ''];
            $micro = $moment === null ? 0 : $moment[6];
        } else {
            $parts = $numeric && Decimal::compare($text, '0') === 0 ? [0, 0, 0, 0, 0, 0, '', false, ''] : Temporal::scanDateTime($text);
            $micro = $parts === null ? 0 : Temporal::micro($parts[6], $truncate);
        }
        if ($parts === null || $parts[1] > 12 || $parts[2] > 31 || $parts[3] > 23 || $parts[4] > 59 || $parts[5] > 59) {
            return $this->problem(ErrorCode::DataTruncated, $text, $column);
        }
        $modes = $context->modes;
        $zero = $parts[0] === 0 && $parts[1] === 0 && $parts[2] === 0;
        if (!Temporal::accepted($parts[0], $parts[1], $parts[2], $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'))) {
            return $this->problem($numeric && !$zero ? ErrorCode::DataTruncated : ErrorCode::OutOfRange, $text, $column);
        }
        $decimals = $to->kind === Kind::Date ? 0 : $to->decimals;
        $moment = Temporal::carry($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], Temporal::scale($micro % 1000000, $decimals, $truncate) + intdiv($micro, 1000000) * 1000000);
        if ($moment === null) {
            if ($context->strict) {
                throw new SqlError(ErrorCode::DatetimeFunctionOverflow, ErrorCode::DatetimeFunctionOverflow->message('datetime'), null, [[ErrorCode::TruncatedWrongValue->value, ErrorCode::TruncatedWrongValueForField->message($this->kind($column), $text, $column->name, $this->store->row)]]);
            }
            $context->warning(ErrorCode::DatetimeFunctionOverflow, 'datetime');

            return $this->problem(ErrorCode::OutOfRange, $text, $column);
        }
        $result = $to->kind === Kind::Date ? Temporal::date($moment[0], $moment[1], $moment[2]) : Temporal::dateTime($moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6], $decimals);
        if ($to->field === Field::Timestamp && !$zero && (strcmp($result, '1970-01-01 00:00:01') < 0 || strcmp($result, '2038-01-19 03:14:08') >= 0)) {
            return $this->problem(ErrorCode::OutOfRange, $text, $column);
        }
        if ($parts[8] !== '') {
            $this->problem(ErrorCode::DataTruncated, $text, $column);
        } elseif ($to->kind === Kind::Date && ($moment[3] !== 0 || $moment[4] !== 0 || $moment[5] !== 0 || $moment[6] !== 0)) {
            $this->dropped($text, $column);
        }

        return $result;
    }

    /**
     * Stores into a TIME column.
     *
     * A string that holds a datetime gives its time, with a note when the date is not zero. An
     * empty string stores midnight.
     *
     * @throws SqlError When the value is refused
     */
    public function time(string $text, Domain $from, ColumnDefinition $column): string
    {
        $decimals = $column->domain->decimals;
        $truncate = $this->store->context->modes->has('TIME_TRUNCATE_FRACTIONAL');
        $dated = $from->kind === Kind::Date || $from->kind === Kind::DateTime;
        if ($from->kind === Kind::String && preg_match('/\A\s*-?\s*\z/', $text) === 1) {
            return Temporal::time(false, 0, 0, 0, 0, $decimals);
        }
        $dropped = false;
        $moment = $from->kind === Kind::Time ? null : Temporal::scanDateTime($text, !$dated);
        if ($moment !== null && ($moment[7] || $dated)) {
            if ($moment[1] > 12 || $moment[2] > 31 || $moment[3] > 23 || $moment[4] > 59 || $moment[5] > 59) {
                return $this->problem(ErrorCode::DataTruncated, $text, $column);
            }
            if (!Temporal::accepted($moment[0], $moment[1], $moment[2], false, false)) {
                return $this->problem(ErrorCode::OutOfRange, $text, $column);
            }
            $dropped = !$dated && ($moment[0] !== 0 || $moment[1] !== 0 || $moment[2] !== 0);
            $time = [false, $moment[3], $moment[4], $moment[5], $moment[6], $moment[8]];
        } else {
            $time = Temporal::scanTime($text);
            if ($time === null) {
                return $this->problem(ErrorCode::DataTruncated, $text, $column);
            }
            if ($time[2] > 59 || $time[3] > 59) {
                return $this->problem(ErrorCode::OutOfRange, $text, $column);
            }
        }
        [$negative, $hours, $minute, $second, $digits, $rest] = $time;
        $micro = Temporal::micro($digits, $truncate);
        if ($hours > 838 || ($hours === 838 && $minute === 59 && $second === 59 && $micro > 0)) {
            if ($rest !== '') {
                $this->problem(ErrorCode::DataTruncated, $text, $column);
            }
            $this->problem(ErrorCode::OutOfRange, $text, $column);

            return Temporal::time($negative, 838, 59, 59, 0, $decimals);
        }
        if ($rest !== '') {
            $this->problem(ErrorCode::DataTruncated, $text, $column);
        } elseif ($dropped) {
            $this->dropped($text, $column);
        }
        $micro = Temporal::scale($micro % 1000000, $decimals, $truncate) + intdiv($micro, 1000000) * 1000000;
        $seconds = $hours * 3600 + $minute * 60 + $second + intdiv($micro, 1000000);

        return Temporal::time($negative && ($seconds > 0 || $micro % 1000000 > 0), intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60, $micro % 1000000, $decimals);
    }

    /**
     * Reports a value the column cannot hold and answers the zero value of the column.
     *
     * Under a strict mode it is an error that names the value and the column; otherwise a warning
     * of the condition: WARN_DATA_TRUNCATED or ER_WARN_DATA_OUT_OF_RANGE.
     *
     * @throws SqlError Under a strict mode
     */
    public function problem(ErrorCode $condition, string $text, ColumnDefinition $column): string
    {
        $context = $this->store->context;
        if ($context->strict) {
            throw new SqlError(ErrorCode::TruncatedWrongValue, ErrorCode::TruncatedWrongValueForField->message($this->kind($column), $text, $column->name, $this->store->row));
        }
        $context->diagnostics->warning($condition, $condition->message($column->name, $this->store->row));

        return match ($column->domain->kind) {
            Kind::Date => '0000-00-00',
            Kind::Time => Temporal::time(false, 0, 0, 0, 0, $column->domain->decimals),
            Kind::Integer, Kind::Decimal, Kind::Double, Kind::String, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => '0000-00-00 00:00:00' . Temporal::fraction(0, $column->domain->decimals),
        };
    }

    /**
     * Notes that a date or a time part was dropped: under a strict mode a note that names the value, otherwise WARN_DATA_TRUNCATED.
     */
    public function dropped(string $text, ColumnDefinition $column): void
    {
        $context = $this->store->context;
        if ($context->strict) {
            $context->diagnostics->note(ErrorCode::TruncatedWrongValue, ErrorCode::TruncatedWrongValueForField->message($this->kind($column), $text, $column->name, $this->store->row));

            return;
        }
        $context->note(ErrorCode::DataTruncated, $column->name, $this->store->row);
    }

    /**
     * Answers the word naming the type of a column in a message: date, time or datetime.
     */
    public function kind(ColumnDefinition $column): string
    {
        return match ($column->domain->field) {
            Field::Date, Field::NewDate => 'date',
            Field::Time => 'time',
            Field::Decimal, Field::Tiny, Field::Short, Field::Long, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::LongLong, Field::Int24, Field::DateTime, Field::Year, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => 'datetime',
        };
    }

    /**
     * Stores into a YEAR column.
     *
     * @throws SqlError When the value is refused
     */
    public function year(int|float|string $value, Domain $from, ColumnDefinition $column): int
    {
        if ($from->kind === Kind::Date || $from->kind === Kind::DateTime) {
            return (int) substr((string) $value, 0, 4);
        }
        if ($from->kind === Kind::Time) {
            return getdate((int) $this->store->context->started)['year'];
        }
        if ($from->kind === Kind::String) {
            $text = (string) $value;
            if (preg_match('/\A\s*[+-]?\.?[0-9]/', $text) !== 1) {
                $this->store->adjust(ErrorCode::TruncatedWrongValueForField, 'integer', $text, $column->name, $this->store->row);

                return 0;
            }
            $read = NumericText::exact($text);
            if (!$read->complete) {
                $this->store->adjust(ErrorCode::DataTruncated, $column->name, $this->store->row);
            }
            $number = Decimal::numeric(Decimal::round($read->number, 0));
            if (Decimal::compare($number, '0') === 0) {
                return strlen($text) === 4 ? 0 : 2000;
            }
        } else {
            $number = $from->kind === Kind::Double ? sprintf('%.0f', round((float) $value, 0, PHP_ROUND_HALF_EVEN)) : Decimal::round((string) Convert::toDecimal($value, $from, $this->store->context), 0);
            if (Decimal::compare($number, '0') === 0) {
                return 0;
            }
        }
        if (Decimal::compare($number, '0') > 0 && Decimal::compare($number, '99') <= 0) {
            $year = (int) $number;

            return $year < 70 ? $year + 2000 : $year + 1900;
        }
        if (Decimal::compare($number, '1901') < 0 || Decimal::compare($number, '2155') > 0) {
            $this->store->adjust(ErrorCode::OutOfRange, $column->name, $this->store->row);

            return 0;
        }

        return (int) $number;
    }

    /**
     * Stores into a JSON column: the text the server returns for the document a text holds.
     *
     * Only a text makes a JSON value: a number or a temporal value is refused ("not a JSON text,
     * may need CAST"), and so is a binary string. A text that is no JSON document is refused
     * with the message of the parser and its position; a document nested too deeply is refused
     * with ER_JSON_DOCUMENT_TOO_DEEP, followed by the error of the parser.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/json.html.
     *
     * @throws SqlError When the value is refused
     */
    public function json(int|float|string $value, Domain $from, ColumnDefinition $column): string
    {
        $name = $this->store->table . '.' . $column->name;
        if ($from->kind === Kind::Json) {
            return (string) $value;
        }
        if ($from->kind !== Kind::String) {
            throw ErrorCode::InvalidJsonText->error('not a JSON text, may need CAST', 0, $name);
        }
        if ($from->collation === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary()) {
            throw ErrorCode::InvalidJsonCharset->error('binary');
        }
        try {
            return Json::canonical((string) $value);
        } catch (JsonSyntax $failure) {
            $error = ErrorCode::InvalidJsonText->message($failure->reason, $failure->position, $name);
            if ($failure->deep) {
                throw new SqlError(ErrorCode::JsonDocumentTooDeep, ErrorCode::JsonDocumentTooDeep->message(), $failure, [[ErrorCode::InvalidJsonText->value, $error]]);
            }

            throw new SqlError(ErrorCode::InvalidJsonText, $error, $failure);
        }
    }
}
