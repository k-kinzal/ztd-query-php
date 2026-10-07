<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

use MySqlMemory\Result\FieldType;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Typing\Collation;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Kind;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Temporal;

/**
 * The binary protocol of prepared statements: rows sent in it, and parameter values read from it.
 *
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_binary_resultset.html.
 *
 * @visibility MySqlMemory
 */
final class Binary
{
    /**
     * Builds a row of the binary protocol from the texts of its values.
     *
     * @param list<ResultColumn> $columns
     * @param list<string|null> $values
     */
    public function row(array $columns, array $values): string
    {
        $bitmap = array_fill(0, intdiv(count($columns) + 9, 8), 0);
        $body = new PayloadWriter();
        foreach ($columns as $index => $column) {
            $value = $values[$index];
            if ($value === null) {
                $bitmap[intdiv($index + 2, 8)] |= 1 << (($index + 2) % 8);
                continue;
            }
            $this->value($body, $column, $value);
        }

        return "\0" . implode('', array_map('chr', $bitmap)) . $body->payload();
    }

    /**
     * Appends one value in the binary form of its column type.
     */
    public function value(PayloadWriter $writer, ResultColumn $column, string $value): void
    {
        $unsigned = $column->unsigned();
        match ($column->type) {
            FieldType::Tiny => $writer->integer((int) $value, 1),
            FieldType::Short, FieldType::Year => $writer->integer((int) $value, 2),
            FieldType::Long, FieldType::Int24 => $writer->integer((int) $value, 4),
            FieldType::LongLong => $writer->integer($unsigned ? Integer::fromUnsignedText($value) : (int) $value, 8),
            FieldType::Float => $writer->bytes(pack('g', (float) $value)),
            FieldType::Double => $writer->bytes(pack('e', (float) $value)),
            FieldType::Date, FieldType::NewDate, FieldType::DateTime, FieldType::Timestamp => $writer->bytes($this->dateTime($value)),
            FieldType::Time => $writer->bytes($this->time($value)),
            default => $writer->lengthEncodedString($value),
        };
    }

    /**
     * Encodes a date or datetime text.
     */
    public function dateTime(string $value): string
    {
        $parts = Temporal::parseDateTime($value) ?? [0, 0, 0, 0, 0, 0, 0, false];
        [$year, $month, $day, $hour, $minute, $second, $micro] = $parts;
        if ($micro !== 0) {
            return pack('CvCCCCCV', 11, $year, $month, $day, $hour, $minute, $second, $micro);
        }
        if ($hour !== 0 || $minute !== 0 || $second !== 0) {
            return pack('CvCCCCC', 7, $year, $month, $day, $hour, $minute, $second);
        }

        return $year === 0 && $month === 0 && $day === 0 ? "\0" : pack('CvCC', 4, $year, $month, $day);
    }

    /**
     * Encodes a time text.
     */
    public function time(string $value): string
    {
        [$negative, $hours, $minute, $second, $micro] = Temporal::parseTime($value) ?? [false, 0, 0, 0, 0];
        if ($hours === 0 && $minute === 0 && $second === 0 && $micro === 0) {
            return "\0";
        }
        $days = intdiv($hours, 24);
        $packed = pack('CCVCCC', $micro === 0 ? 8 : 12, $negative ? 1 : 0, $days, $hours % 24, $minute, $second);

        return $micro === 0 ? $packed : $packed . pack('V', $micro);
    }

    /**
     * Reads the values of the parameters of COM_STMT_EXECUTE, each with the domain of its type.
     *
     * @param list<int> $types The type and flags of each parameter, as last bound
     * @return array{list<array{int|float|string|null, Domain}>, list<int>} The values, and the types read
     */
    public function parameters(PayloadReader $reader, int $count, array $types, Collation $collation): array
    {
        if ($count === 0) {
            return [[], $types];
        }
        $bitmap = $reader->bytes(intdiv($count + 7, 8));
        if ($reader->integer(1) === 1) {
            $types = [];
            for ($i = 0; $i < $count; $i++) {
                $types[] = $reader->integer(2);
            }
        }
        $values = [];
        for ($i = 0; $i < $count; $i++) {
            if ((ord($bitmap[intdiv($i, 8)]) >> ($i % 8)) & 1) {
                $values[] = [null, Domain::null()];
                continue;
            }
            $values[] = $this->parameter($reader, $types[$i] ?? FieldType::VarString->value, $collation);
        }

        return [$values, $types];
    }

    /**
     * Reads one parameter value of a type.
     *
     * @return array{int|float|string|null, Domain}
     */
    public function parameter(PayloadReader $reader, int $type, Collation $collation): array
    {
        $unsigned = ($type & 0x8000) !== 0;
        $field = FieldType::tryFrom($type & 0xFF) ?? FieldType::VarString;
        $integer = static function (int $bytes, int $value) use ($unsigned): int {
            if ($unsigned || $bytes === 8) {
                return $value;
            }
            $bits = $bytes * 8;

            return $value >= (1 << ($bits - 1)) ? $value - (1 << $bits) : $value;
        };

        return match ($field) {
            FieldType::Tiny => [$integer(1, $reader->integer(1)), Domain::integer(FieldType::LongLong, 4, $unsigned)->withNullable(false)],
            FieldType::Short, FieldType::Year => [$integer(2, $reader->integer(2)), Domain::integer(FieldType::LongLong, 6, $unsigned)->withNullable(false)],
            FieldType::Long, FieldType::Int24 => [$integer(4, $reader->integer(4)), Domain::integer(FieldType::LongLong, 11, $unsigned)->withNullable(false)],
            FieldType::LongLong => [$reader->integer(8), Domain::integer(FieldType::LongLong, 20, $unsigned)->withNullable(false)],
            FieldType::Float => [(float) (unpack('g', $reader->bytes(4))[1] ?? 0.0), Domain::double()->withNullable(false)],
            FieldType::Double => [(float) (unpack('e', $reader->bytes(8))[1] ?? 0.0), Domain::double()->withNullable(false)],
            FieldType::Null => [null, Domain::null()],
            default => $this->text($reader->lengthEncodedString(), $field, $collation),
        };
    }

    /**
     * Answers a parameter sent as text, with the domain of a string or of an exact number.
     *
     * @return array{string, Domain}
     */
    public function text(string $value, FieldType $field, Collation $collation): array
    {
        if ($field === FieldType::NewDecimal || $field === FieldType::Decimal) {
            return [$value, Domain::decimal(max(1, strlen(str_replace(['-', '.'], '', $value))), strpos($value, '.') === false ? 0 : strlen($value) - strpos($value, '.') - 1)->withNullable(false)];
        }
        $collation = $field === FieldType::Blob || $field === FieldType::LongBlob || $field === FieldType::MediumBlob || $field === FieldType::TinyBlob ? Collation::Binary : $collation;

        return [$value, (new Domain(Kind::String, FieldType::VarString, mb_strlen($value, 'UTF-8'), Domain::NOT_FIXED, false, $collation, false))];
    }
}
