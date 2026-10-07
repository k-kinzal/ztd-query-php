<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Result\FieldType;
use MySqlMemory\Typing\Collation;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Kind;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\NumericText;

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
     */
    public function __construct(public readonly Context $context, public int $row = 1)
    {
    }

    /**
     * Converts a value of a domain into the value a column stores.
     *
     * @throws \MySqlMemory\Error\SqlError When the value is refused
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
     * @throws \MySqlMemory\Error\SqlError Under a strict mode
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
     * @return array{string, string}
     */
    public function range(Domain $domain): array
    {
        $bits = match ($domain->field) {
            FieldType::Tiny => 8,
            FieldType::Short => 16,
            FieldType::Int24 => 24,
            FieldType::Long => 32,
            default => 64,
        };
        if ($domain->unsigned) {
            return ['0', bcsub(bcpow('2', (string) $bits), '1')];
        }

        return [bcmul(bcpow('2', (string) ($bits - 1)), '-1'), bcsub(bcpow('2', (string) ($bits - 1)), '1')];
    }

    /**
     * Stores into a DECIMAL column.
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
        if ($column->domain->field === FieldType::Float) {
            $single = unpack('g', pack('g', $number));
            $number = is_array($single) ? (float) $single[1] : $number;
            if (is_infinite($number)) {
                $this->adjust(ErrorCode::OutOfRange, $column->name, $this->row);
                $number = $number > 0 ? 3.4028234663852886e38 : -3.4028234663852886e38;
            }
        }

        return $number;
    }

    /**
     * Stores into a string column: CHAR, VARCHAR, TEXT, BINARY, BLOB, ENUM or SET.
     */
    public function string(int|float|string $value, Domain $from, ColumnDefinition $column): string
    {
        $to = $column->domain;
        $text = (string) Convert::toText($value, $from);
        if ($to->field === FieldType::Enum || $to->field === FieldType::Set) {
            return (new Members($this))->value($text, $from, $column);
        }
        $charset = $to->collation->charset();
        $limit = $to->length;
        if ($charset->length($text) > $limit) {
            $kept = $charset->maxLength() === 1 || !mb_check_encoding($text, 'UTF-8') ? substr($text, 0, $limit) : mb_substr($text, 0, $limit, 'UTF-8');
            $rest = substr($text, strlen($kept));
            if (trim($rest, ' ') === '' && $to->collation !== Collation::Binary) {
                $this->context->note(ErrorCode::DataTruncated, $column->name, $this->row);
            } else {
                $this->adjust($this->context->strict ? ErrorCode::DataTooLong : ErrorCode::DataTruncated, $column->name, $this->row);
            }
            $text = $kept;
        }
        if ($to->field === FieldType::String) {
            return $to->collation === Collation::Binary ? str_pad($text, $limit, "\0") : rtrim($text, ' ');
        }

        return $text;
    }

    /**
     * Stores into a BIT column: the bytes of the number, refusing a number of more bits.
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
