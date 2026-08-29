<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Reads one column declaration from a PostgreSQL CREATE TABLE statement.
 *
 * Three things make PostgreSQL's declarations their own subject. SERIAL is not
 * a type but a shorthand the server expands into an integer column with a
 * sequence behind it, so a fixture must leave such a column alone. Several
 * types are spelled as more than one word, so a type cannot be read as the
 * first word. And a default may be an expression, a function call, or a value
 * with a cast written after it, none of which is a value to be read.
 */
final class PostgreSqlColumnReader
{
    private const SERIAL_TYPES = [
        'SERIAL' => 'INTEGER',
        'BIGSERIAL' => 'BIGINT',
        'SMALLSERIAL' => 'SMALLINT',
    ];

    private const MULTI_WORD_TYPES = [
        'DOUBLE PRECISION',
        'TIMESTAMP WITH TIME ZONE',
        'TIMESTAMP WITHOUT TIME ZONE',
        'TIME WITH TIME ZONE',
        'TIME WITHOUT TIME ZONE',
        'CHARACTER VARYING',
    ];

    /**
     * Reads one declaration as a column.
     *
     * @param string $definition One column declaration
     * @param list<string> $tablePrimaryKeys Columns a table-level PRIMARY KEY names
     *
     * @return ColumnDefinition|null The column, or null when the text does not begin with a name
     */
    public function read(string $definition, array $tablePrimaryKeys): ?ColumnDefinition
    {
        if (preg_match('/^"?(\w+)"?\s*(.*)/is', $definition, $matches) !== 1) {
            return null;
        }

        $name = $matches[1];
        $rest = trim($matches[2]);
        $type = $this->type($rest);
        $autoIncrement = isset(self::SERIAL_TYPES[strtoupper($type)]);
        if ($autoIncrement) {
            $type = self::SERIAL_TYPES[strtoupper($type)];
        }

        ['type' => $type, 'length' => $length, 'precision' => $precision, 'scale' => $scale]
            = $this->declaredSize($rest, $type, $autoIncrement);
        if (str_ends_with($type, '[]')) {
            $type = substr($type, 0, -2) . '_ARRAY';
        }

        $upperRest = strtoupper($rest);
        $isPrimaryKey = str_contains($upperRest, 'PRIMARY KEY') || in_array($name, $tablePrimaryKeys, true);

        return new ColumnDefinition(
            name: $name,
            type: $type,
            length: $length,
            precision: $precision,
            scale: $scale,
            nullable: !$isPrimaryKey && !str_contains($upperRest, 'NOT NULL'),
            unsigned: false,
            default: $this->defaultValue($rest),
            autoIncrement: $autoIncrement,
            generated: preg_match('/\bGENERATED\s+/i', $rest) === 1,
            enumValues: null,
        );
    }

    /**
     * Reads the size a type declaration writes in brackets.
     *
     * A declaration carries at most one size, written as either a length or a
     * precision and scale. Which of them it means is the type's answer, not
     * the number's: an exact numeric counts digits even when it names only
     * one, and everything else counts characters. A serial keeps the type its
     * name stood for, so the declared word is not allowed to replace it.
     *
     * @param string $rest The declaration, from the type onwards
     * @param string $type The type read so far
     * @param bool $autoIncrement Whether the name stood for a serial
     *
     * @return array{type: string, length: int|null, precision: int|null, scale: int|null} The type and the size it declares
     */
    public function declaredSize(string $rest, string $type, bool $autoIncrement): array
    {
        if (preg_match('/^(\w+(?:\s+\w+)?)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $rest, $matches) !== 1) {
            return ['type' => $type, 'length' => null, 'precision' => null, 'scale' => null];
        }

        $declared = strtoupper($matches[1]);
        $sized = $autoIncrement ? $type : $declared;
        if (isset($matches[3])) {
            return ['type' => $sized, 'length' => null, 'precision' => (int) $matches[2], 'scale' => (int) $matches[3]];
        }
        if ($this->isExactNumeric($declared)) {
            return ['type' => $sized, 'length' => null, 'precision' => (int) $matches[2], 'scale' => 0];
        }

        return ['type' => $sized, 'length' => (int) $matches[2], 'precision' => null, 'scale' => null];
    }

    /**
     * Answers the type a declaration names.
     *
     * The multi-word types are looked for first, because reading the first
     * word alone would turn DOUBLE PRECISION into DOUBLE and TIMESTAMP WITH
     * TIME ZONE into TIMESTAMP, which are different types.
     *
     * @param string $rest Everything after the column name
     *
     * @return string The type name in capitals, or TEXT when none was declared
     */
    public function type(string $rest): string
    {
        if ($rest === '') {
            return 'TEXT';
        }

        $upperRest = strtoupper($rest);
        foreach (self::MULTI_WORD_TYPES as $multiWord) {
            if (str_starts_with($upperRest, $multiWord)) {
                return $multiWord;
            }
        }
        if (preg_match('/^(\w+(?:\[\])?)/i', $rest, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'TEXT';
    }

    /**
     * Reports whether a type counts digits rather than characters.
     *
     * A single number after NUMERIC is a precision; after VARCHAR it is a
     * length. Nothing else distinguishes the two.
     *
     * @param string $type Type name as declared
     *
     * @return bool True when the number in parentheses is a precision
     */
    public function isExactNumeric(string $type): bool
    {
        return in_array(strtoupper($type), ['DECIMAL', 'NUMERIC', 'DEC'], true);
    }

    /**
     * Answers the value a DEFAULT clause names, read as the type it is written as.
     *
     * A default that is an expression, a function call, or carries an explicit
     * cast is carried through as written: the server evaluates it, and reading
     * it as a value would lose what it means.
     *
     * @param string $rest Everything after the column name
     *
     * @return int|float|string|bool|null The default, or null when none was declared
     */
    public function defaultValue(string $rest): int|float|string|bool|null
    {
        $pattern = '/\bDEFAULT\s+(.+?)'
            . '(?:\s+(?:NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|CONSTRAINT|GENERATED)|$)/is';
        if (preg_match($pattern, $rest, $matches) !== 1) {
            return null;
        }

        $written = trim((string) preg_replace(
            '/\s+(NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|CONSTRAINT).*$/i',
            '',
            trim($matches[1]),
        ));

        if (str_starts_with($written, '(') && str_ends_with($written, ')')) {
            return $written;
        }
        if (preg_match('/^\w+\(.*\)$/i', $written) === 1 || str_contains($written, '::')) {
            return $written;
        }
        if (preg_match("/^['\"](.*)['\"]\s*$/s", $written, $stringMatches) === 1) {
            return $stringMatches[1];
        }
        if (strtoupper($written) === 'NULL') {
            return null;
        }
        if (strtoupper($written) === 'TRUE') {
            return true;
        }
        if (strtoupper($written) === 'FALSE') {
            return false;
        }
        if (is_numeric($written)) {
            return str_contains($written, '.') ? (float) $written : (int) $written;
        }

        return $written;
    }
}
