<?php

declare(strict_types=1);

namespace MySqlMemory\Protocol;

use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

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
            Field::Tiny => $writer->integer((int) $value, 1),
            Field::Short, Field::Year => $writer->integer((int) $value, 2),
            Field::Long, Field::Int24 => $writer->integer((int) $value, 4),
            Field::LongLong => $writer->integer($unsigned ? Integer::fromUnsignedText($value) : (int) $value, 8),
            Field::Float => $writer->bytes(pack('g', (float) $value)),
            Field::Double => $writer->bytes(pack('e', (float) $value)),
            Field::Date, Field::NewDate, Field::DateTime, Field::Timestamp => $writer->bytes($this->dateTime($value)),
            Field::Time => $writer->bytes($this->time($value)),
            Field::Decimal, Field::Null, Field::VarChar, Field::Bit, Field::Vector, Field::Json, Field::NewDecimal, Field::Enum, Field::Set,
            Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob, Field::VarString, Field::String, Field::Geometry => $writer->lengthEncodedString($value),
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
     *
     * @throws MalformedPacket When the payload ends before a value
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
            if (((ord($bitmap[intdiv($i, 8)]) >> ($i % 8)) & 1) === 1) {
                $values[] = [null, Domain::null()];
                continue;
            }
            $values[] = $this->parameter($reader, $types[$i] ?? Field::VarString->value, $collation);
        }

        return [$values, $types];
    }

    /**
     * Reads one parameter value of a type.
     *
     * @return array{int|float|string|null, Domain}
     *
     * @throws MalformedPacket When the payload ends before the value
     */
    public function parameter(PayloadReader $reader, int $type, Collation $collation): array
    {
        $unsigned = ($type & 0x8000) !== 0;
        $field = Field::tryFrom($type & 0xFF) ?? Field::VarString;
        $integer = static function (int $bytes, int $value) use ($unsigned): int {
            if ($unsigned || $bytes === 8) {
                return $value;
            }
            $bits = $bytes * 8;

            return $value >= (1 << ($bits - 1)) ? $value - (1 << $bits) : $value;
        };

        return match ($field) {
            Field::Tiny => [$integer(1, $reader->integer(1)), Domain::integer(Field::LongLong, 4, $unsigned)->withNullable(false)],
            Field::Short, Field::Year => [$integer(2, $reader->integer(2)), Domain::integer(Field::LongLong, 6, $unsigned)->withNullable(false)],
            Field::Long, Field::Int24 => [$integer(4, $reader->integer(4)), Domain::integer(Field::LongLong, 11, $unsigned)->withNullable(false)],
            Field::LongLong => [$reader->integer(8), Domain::integer(Field::LongLong, 20, $unsigned)->withNullable(false)],
            Field::Float => [$this->float($reader, 'g', 4), Domain::double()->withNullable(false)],
            Field::Double => [$this->float($reader, 'e', 8), Domain::double()->withNullable(false)],
            Field::Null => [null, Domain::null()],
            Field::Decimal, Field::Timestamp, Field::Date, Field::Time, Field::DateTime, Field::NewDate, Field::VarChar, Field::Bit, Field::Vector,
            Field::Json, Field::NewDecimal, Field::Enum, Field::Set, Field::TinyBlob, Field::MediumBlob, Field::LongBlob, Field::Blob,
            Field::VarString, Field::String, Field::Geometry => $this->text($reader->lengthEncodedString(), $field, $collation),
        };
    }

    /**
     * Reads a little-endian IEEE 754 number of four or eight bytes, unpacked with a pack() format.
     *
     * @param string $format `g` for a FLOAT, `e` for a DOUBLE
     * @param int $length The number of bytes the format reads
     *
     * @throws MalformedPacket When the payload ends before the number
     */
    public function float(PayloadReader $reader, string $format, int $length): float
    {
        $unpacked = unpack($format, $reader->bytes($length));
        $value = $unpacked === false ? null : $unpacked[1] ?? null;

        return is_float($value) ? $value : 0.0;
    }

    /**
     * Answers a parameter sent as text, with the domain of a string or of an exact number.
     *
     * @return array{string, Domain}
     */
    public function text(string $value, Field $field, Collation $collation): array
    {
        if ($field === Field::NewDecimal || $field === Field::Decimal) {
            return [$value, Domain::decimal(max(1, strlen(str_replace(['-', '.'], '', $value))), strpos($value, '.') === false ? 0 : strlen($value) - strpos($value, '.') - 1)->withNullable(false)];
        }
        $collation = $field === Field::Blob || $field === Field::LongBlob || $field === Field::MediumBlob || $field === Field::TinyBlob ? Collation::binary() : $collation;

        return [$value, (new Domain(Kind::String, Field::VarString, mb_strlen($value, 'UTF-8'), Domain::NOT_FIXED, false, $collation, false))];
    }
}
