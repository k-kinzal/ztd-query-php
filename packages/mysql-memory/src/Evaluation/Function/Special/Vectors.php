<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The vector functions of MySQL 9: STRING_TO_VECTOR (TO_VECTOR), VECTOR_TO_STRING (FROM_VECTOR) and VECTOR_DIM.
 *
 * A vector is a list of at most 16383 single-precision floats, held as their little-endian bytes.
 * Its text is a bracketed list of numbers separated by commas, blanks allowed around each part; a
 * number is read as C reads a float, hexadecimal included, and must be finite and not below the
 * smallest normal float in magnitude unless it is zero. A text that does not read is
 * ER_DATA_INCOMPATIBLE_WITH_VECTOR quoting it, too many numbers ER_DATA_OUT_OF_RANGE. A vector
 * is written with six significant digits in exponent form. Only a binary string is a vector:
 * another type is ER_WRONG_ARGUMENTS, and a binary string whose length is not a positive
 * multiple of four ER_DATA_INCOMPATIBLE_WITH_VECTOR quoting its bytes up to the first zero byte
 * (verified on a live 9.1.0 server).
 * Source: https://dev.mysql.com/doc/refman/9.1/en/vector-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Vectors
{
    /**
     * The most dimensions of a vector.
     */
    public const DIMENSIONS = 16383;

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('STRING_TO_VECTOR', 1, 1, fn (Frame $f, array $a): ?string => $this->vector($f, $a[0])),
            new Routine('TO_VECTOR', 1, 1, fn (Frame $f, array $a): ?string => $this->vector($f, $a[0])),
            new Routine('VECTOR_TO_STRING', 1, 1, fn (Frame $f, array $a): ?string => $this->text($f, $a[0])),
            new Routine('FROM_VECTOR', 1, 1, fn (Frame $f, array $a): ?string => $this->text($f, $a[0])),
            new Routine('VECTOR_DIM', 1, 1, fn (Frame $f, array $a): ?int => ($bytes = $this->bytes($f, $a[0], 'vector_dim')) === null ? null : intdiv(strlen($bytes), 4)),
        ];
    }

    /**
     * STRING_TO_VECTOR(text): the bytes of the vector a text writes.
     *
     * @throws SqlError When the argument is no string, or its text no vector
     */
    public function vector(Frame $frame, Evaluable $argument): ?string
    {
        $domain = $argument->domain();
        if (!in_array($domain->kind, [Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Null], true)) {
            throw StatementError::WrongArguments->error('to_vector');
        }
        $text = Convert::toText($argument->evaluate($frame), $domain);
        if ($text === null) {
            return null;
        }
        $numbers = $this->numbers($text);
        if ($numbers === null) {
            throw $this->incompatible($text, $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4'));
        }
        if (count($numbers) > self::DIMENSIONS) {
            throw DataError::DataOutOfRange->error(substr($text, 0, 32) . '... ', 'to_vector');
        }

        return pack('g*', ...$numbers);
    }

    /**
     * Reads the numbers of the text of a vector, or answers null when it is none.
     *
     * @return list<float>|null
     */
    public function numbers(string $text): ?array
    {
        $blank = " \t\n\v\f\r";
        $trimmed = trim($text, $blank);
        if (strlen($trimmed) < 2 || $trimmed[0] !== '[' || $trimmed[strlen($trimmed) - 1] !== ']') {
            return null;
        }
        $numbers = [];
        foreach (explode(',', substr($trimmed, 1, -1)) as $part) {
            $number = $this->number(trim($part, $blank));
            if ($number === null) {
                return null;
            }
            $numbers[] = $number;
        }

        return $numbers;
    }

    /**
     * Reads one number of a vector as C reads a float, or answers null when it is none or out of the range of a normal float.
     */
    public function number(string $text): ?float
    {
        if (preg_match('/\A([+-]?)(?:0[xX]((?:[0-9a-fA-F]+\.?[0-9a-fA-F]*|\.[0-9a-fA-F]+))(?:[pP]([+-]?[0-9]+))?|((?:[0-9]+\.?[0-9]*|\.[0-9]+)(?:[eE][+-]?[0-9]+)?))\z/', $text, $parts) !== 1) {
            return null;
        }
        if (($parts[4] ?? '') !== '') {
            $value = (float) $parts[4];
        } else {
            [$whole, $fraction] = explode('.', ($parts[2] ?? '') . '.') + [1 => ''];
            $digits = $whole . $fraction;
            $value = 0.0;
            foreach (str_split($digits) as $digit) {
                $value = $value * 16 + hexdec($digit);
            }
            $value *= 2 ** ((int) ($parts[3] ?? 0) - 4 * strlen($fraction));
        }
        $value = $parts[1] === '-' ? -$value : $value;
        $single = unpack('g', pack('g', $value));
        $rounded = is_array($single) && is_float($single[1]) ? $single[1] : INF;
        if (is_infinite($rounded) || is_nan($rounded) || ($value !== 0.0 && abs($rounded) < 1.1754943508222875E-38)) {
            return null;
        }

        return $rounded;
    }

    /**
     * VECTOR_TO_STRING(vector): the text of a vector.
     *
     * @throws SqlError When the argument is no vector
     */
    public function text(Frame $frame, Evaluable $argument): ?string
    {
        $bytes = $this->bytes($frame, $argument, 'from_vector');
        if ($bytes === null) {
            return null;
        }
        if (strlen($bytes) > 4 * self::DIMENSIONS) {
            $cut = strstr($bytes, "\0", true);

            throw DataError::DataOutOfRange->error($cut === false ? $bytes : $cut, 'from_vector');
        }
        $values = unpack('g*', $bytes);

        return '[' . implode(',', array_map($this->format(...), $values === false ? [] : array_values(array_filter($values, is_float(...))))) . ']';
    }

    /**
     * Writes a float as C writes it with `%.5e`.
     */
    public function format(float $value): string
    {
        $negative = (ord(pack('E', $value)[0]) & 0x80) !== 0;
        if (is_nan($value)) {
            return $negative ? '-nan' : 'nan';
        }
        if (is_infinite($value)) {
            return $negative ? '-inf' : 'inf';
        }
        $text = sprintf('%.5e', $value);
        [$mantissa, $exponent] = explode('e', $text);
        $power = (int) $exponent;

        return ($negative && !str_starts_with($mantissa, '-') ? '-' : '') . $mantissa . 'e' . ($power < 0 ? '-' : '+') . str_pad((string) abs($power), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Reads the bytes of a vector argument, or answers null when it is NULL.
     *
     * @throws SqlError When the argument is no binary string, or its length no positive multiple of four
     */
    public function bytes(Frame $frame, Evaluable $argument, string $function): ?string
    {
        $domain = $argument->domain();
        if ($domain->kind === Kind::Null) {
            return null;
        }
        if ($domain->kind !== Kind::String || (!$domain->collation->bytes() && $domain->field !== Field::Vector)) {
            throw StatementError::WrongArguments->error($function);
        }
        $bytes = Convert::toText($argument->evaluate($frame), $domain);
        if ($bytes === null) {
            return null;
        }
        if ($bytes === '' || strlen($bytes) % 4 !== 0) {
            throw $this->incompatible($bytes, Charset::known('binary'));
        }

        return $bytes;
    }

    /**
     * Answers ER_DATA_INCOMPATIBLE_WITH_VECTOR quoting a text up to its first zero byte, the message at most 511 bytes.
     */
    public function incompatible(string $text, Charset $charset): SqlError
    {
        $cut = strstr($text, "\0", true);
        $quoted = $charset->name === 'binary' ? ($cut === false ? $text : $cut) : Encoding::convert($cut === false ? $text : $cut, $charset, Charset::known('utf8mb4'));

        return new SqlError(DataError::InvalidVector, mb_strcut(DataError::InvalidVector->message($quoted), 0, 511, 'UTF-8'));
    }
}
