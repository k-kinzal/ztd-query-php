<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\NumericText;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Stores a value into a column, as the server converts a value for the field it writes.
 *
 * A value the column cannot hold is adjusted: an integer outside the range of its type is
 * clipped (ER_WARN_DATA_OUT_OF_RANGE), a number with more decimals is rounded (a note), a
 * string that is too long is cut (WARN_DATA_TRUNCATED), a string that is no number reads as
 * zero (ER_TRUNCATED_WRONG_VALUE_FOR_FIELD). Under a strict mode each adjustment but rounding is
 * an error instead; cutting trailing spaces is only a note.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/out-of-range-and-overflow.html.
 *
 * @visibility MySqlMemory
 */
final class Store
{
    /**
     * @param Context $context The statement writing, whose mode decides between warnings and errors
     * @param int $row The number of the row being written, counted from 1, for messages
     * @param string $table The name the statement writes the table under, which qualifies the column an invalid JSON text is reported for; empty when it writes several tables
     */
    public function __construct(public readonly Context $context, public int $row = 1, public readonly string $table = '')
    {
    }

    /**
     * Converts a value of a domain into the value a column stores.
     *
     * @throws SqlError When the value is refused
     */
    public function value(int|float|string|null $value, Domain $from, ColumnDefinition $column): int|float|string|null
    {
        if ($value === null) {
            return null;
        }
        $to = $column->domain;

        return match ($to->kind) {
            Kind::Integer => $this->integer($value, $from, $column),
            Kind::Decimal => $this->decimal($value, $from, $column),
            Kind::Double => $this->real($value, $from, $column),
            Kind::String => $this->string($value, $from, $column),
            Kind::Date, Kind::DateTime, Kind::Time, Kind::Year => (new Times($this))->value($value, $from, $column),
            Kind::Json => (new Times($this))->json($value, $from, $column),
            Kind::Bit => $this->bit($value, $from, $column),
            Kind::Null => null,
        };
    }

    /**
     * Raises or records an adjustment: an error under a strict mode, else a warning.
     *
     * @throws SqlError Under a strict mode
     */
    public function adjust(ErrorCode $code, string|int ...$arguments): void
    {
        if ($this->context->strict) {
            throw $code->error(...$arguments);
        }
        $this->context->warning($code, ...$arguments);
    }

    /**
     * Stores into an integer column.
     *
     * @throws SqlError When the value is refused
     */
    public function integer(int|float|string $value, Domain $from, ColumnDefinition $column): int
    {
        $to = $column->domain;
        if ($from->kind === Kind::String) {
            $read = NumericText::exact((string) $value);
            if (!$read->complete) {
                if (NumericText::real((string) $value)->number === '0' && preg_match('/\A\s*[+-]?\.?[0-9]/', (string) $value) !== 1) {
                    $this->adjust(ErrorCode::TruncatedWrongValueForField, 'integer', (string) $value, $column->name, $this->row);
                } else {
                    $this->adjust(ErrorCode::DataTruncated, $column->name, $this->row);
                }
            }
            $number = Decimal::round($read->number, 0);
        } elseif ($from->kind === Kind::Double) {
            $number = sprintf('%.0f', round((float) $value, 0, PHP_ROUND_HALF_EVEN));
        } else {
            $number = Decimal::round((string) Convert::toDecimal($value, $from, $this->context), 0);
        }
        $number = Decimal::numeric($number);
        [$low, $high] = $this->range($to);
        if (bccomp($number, $low, 0) < 0 || bccomp($number, $high, 0) > 0) {
            $this->adjust(ErrorCode::OutOfRange, $column->name, $this->row);
            $number = bccomp($number, $low, 0) < 0 ? $low : $high;
        }

        return $to->unsigned ? Integer::fromUnsignedText($number) : (int) $number;
    }

    /**
     * Answers the lowest and highest value of an integer domain.
     *
     * @return array{numeric-string, numeric-string}
     */
    public function range(Domain $domain): array
    {
        $bits = match ($domain->field) {
            Field::Tiny => 8,
            Field::Short => 16,
            Field::Int24 => 24,
            Field::Long => 32,
            Field::Decimal, Field::Float, Field::Double, Field::Null, Field::Timestamp, Field::LongLong, Field::Date, Field::Time, Field::DateTime, Field::Year, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => 64,
        };
        if ($domain->unsigned) {
            return ['0', bcsub(bcpow('2', (string) $bits), '1')];
        }

        return [bcmul(bcpow('2', (string) ($bits - 1)), '-1'), bcsub(bcpow('2', (string) ($bits - 1)), '1')];
    }

    /**
     * Stores into a DECIMAL column.
     *
     * @throws SqlError When the value is refused
     */
    public function decimal(int|float|string $value, Domain $from, ColumnDefinition $column): string
    {
        $to = $column->domain;
        if ($from->kind === Kind::String) {
            $read = NumericText::exact((string) $value);
            if (!$read->complete) {
                $this->adjust(preg_match('/\A\s*[+-]?\.?[0-9]/', (string) $value) === 1 ? ErrorCode::DataTruncated : ErrorCode::TruncatedWrongValueForField, ...(preg_match('/\A\s*[+-]?\.?[0-9]/', (string) $value) === 1 ? [$column->name, $this->row] : ['decimal', (string) $value, $column->name, $this->row]));
            }
            $number = $read->number;
        } else {
            $number = (string) Convert::toDecimal($value, $from, $this->context);
        }
        $rounded = Decimal::round($number, $to->decimals);
        if (Decimal::compare($rounded, $number) !== 0) {
            $this->context->note(ErrorCode::DataTruncated, $column->name, $this->row);
        }
        $digits = $to->precision() - $to->decimals;
        $largest = ($digits > 0 ? str_repeat('9', $digits) : '0') . ($to->decimals > 0 ? '.' . str_repeat('9', $to->decimals) : '');
        if (Decimal::compare(ltrim($rounded, '-'), $largest) > 0 || ($to->unsigned && Decimal::compare($rounded, '0') < 0)) {
            $this->adjust(ErrorCode::OutOfRange, $column->name, $this->row);

            return $to->unsigned && Decimal::compare($rounded, '0') < 0 ? Decimal::round('0', $to->decimals) : (str_starts_with($rounded, '-') ? '-' . $largest : $largest);
        }

        return $rounded;
    }

    /**
     * Stores into a FLOAT or DOUBLE column; a FLOAT holds single precision.
     *
     * @throws SqlError When the value is refused
     */
    public function real(int|float|string $value, Domain $from, ColumnDefinition $column): float
    {
        if ($from->kind === Kind::String) {
            $read = NumericText::real((string) $value);
            if (!$read->complete) {
                $this->adjust(preg_match('/\A\s*[+-]?\.?[0-9]/', (string) $value) === 1 ? ErrorCode::DataTruncated : ErrorCode::TruncatedWrongValueForField, ...(preg_match('/\A\s*[+-]?\.?[0-9]/', (string) $value) === 1 ? [$column->name, $this->row] : ['double', (string) $value, $column->name, $this->row]));
            }
            $number = (float) $read->number;
        } else {
            $number = (float) Convert::toDouble($value, $from, $this->context);
        }
        if ($column->domain->decimals < Domain::NOT_FIXED) {
            $number = round($number, $column->domain->decimals);
        }
        if ($column->domain->field === Field::Float) {
            $single = unpack('g', pack('g', $number));
            $number = is_array($single) && is_float($single[1]) ? $single[1] : $number;
            if (is_infinite($number)) {
                $this->adjust(ErrorCode::OutOfRange, $column->name, $this->row);
                $number = $number > 0 ? 3.4028234663852886e38 : -3.4028234663852886e38;
            }
        }

        return $number;
    }

    /**
     * Stores into a string column: CHAR, VARCHAR, TEXT, BINARY, BLOB, ENUM or SET.
     *
     * @throws SqlError When the value is refused
     */
    public function string(int|float|string $value, Domain $from, ColumnDefinition $column): string
    {
        $to = $column->domain;
        $text = $this->encoded((string) Convert::toText($value, $from), $from, $column);
        if ($to->field === Field::Enum || $to->field === Field::Set) {
            return (new Members($this))->value($text, $from, $column);
        }
        $charset = $to->collation->charset;
        $limit = $to->length;
        if (Encoding::length($text, $charset) > $limit) {
            $kept = Encoding::slice($text, 0, $limit, $charset);
            $rest = substr($text, strlen($kept));
            if (trim($rest, ' ') === '' && $to->collation !== Collation::binary()) {
                $this->context->note(ErrorCode::DataTruncated, $column->name, $this->row);
            } else {
                $this->adjust($this->context->strict ? ErrorCode::DataTooLong : ErrorCode::DataTruncated, $column->name, $this->row);
            }
            $text = $kept;
        }
        if ($to->field === Field::String) {
            return $to->collation === Collation::binary() ? str_pad($text, $limit, "\0") : rtrim($text, ' ');
        }

        return $text;
    }

    /**
     * Converts a text into the character set of a column.
     *
     * A character the set cannot hold is stored as `?`; bytes of a binary string that are no
     * character of the set end the text. Either is ER_TRUNCATED_WRONG_VALUE_FOR_FIELD, quoting the
     * bytes from the first such character.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-conversion.html.
     *
     * @throws SqlError When the value is refused
     */
    public function encoded(string $text, Domain $from, ColumnDefinition $column): string
    {
        $source = $from->kind === Kind::String ? $from->collation->charset : Charset::known('utf8mb4');
        $target = $column->domain->collation->charset;
        if ($source === Charset::binary()) {
            $valid = Encoding::valid($text, $target) ? strlen($text) : Encoding::prefix($text, $target);
            $converted = substr($text, 0, $valid);
        } else {
            $converted = Encoding::convert($text, $source, $target);
            $valid = $target === Charset::binary() || Encoding::convert($converted, $target, $source) === $text ? strlen($text) : Encoding::convertible($text, $source, $target);
        }
        if ($valid < strlen($text)) {
            $rest = substr($text, $valid);
            $quoted = (string) preg_replace_callback('/[^\x20-\x7E]/', static fn (array $byte): string => sprintf('\\x%02X', ord($byte[0])), substr($rest, 0, 6));
            $this->adjust(ErrorCode::TruncatedWrongValueForField, 'string', $quoted . (strlen($rest) > 6 ? '...' : ''), $column->name, $this->row);
        }

        return $converted;
    }

    /**
     * Stores into a BIT column: the bytes of the number, refusing a number of more bits.
     *
     * @throws SqlError When the value is refused
     */
    public function bit(int|float|string $value, Domain $from, ColumnDefinition $column): string
    {
        $bits = $column->domain->length;
        $number = $from->kind === Kind::String ? Convert::bits((string) $value) : (int) Convert::toInteger($value, $from, $this->context, true);
        if ($bits < 64 && ($number < 0 || $number >= (1 << $bits))) {
            $this->adjust($this->context->strict ? ErrorCode::DataTooLong : ErrorCode::OutOfRange, $column->name, $this->row);
            $number = $bits >= 63 ? -1 : (1 << $bits) - 1;
        }
        $bytes = '';
        for ($i = (int) ceil($bits / 8) - 1; $i >= 0; $i--) {
            $bytes .= chr(($number >> ($i * 8)) & 0xFF);
        }

        return $bytes;
    }
}
