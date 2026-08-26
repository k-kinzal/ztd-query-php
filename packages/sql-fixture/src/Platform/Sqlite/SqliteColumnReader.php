<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Reads one column declaration from a SQLite CREATE TABLE statement.
 *
 * SQLite accepts a column with no type at all, and accepts any type name it
 * has never heard of, so reading one is a matter of taking the name and
 * whatever follows rather than matching against a list. Only AUTOINCREMENT on
 * an INTEGER PRIMARY KEY makes the server fill the column in, which is what
 * decides whether a fixture should leave it alone.
 */
final class SqliteColumnReader
{
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
        if (preg_match('/^["`]?(\w+)["`]?\s*(.*)/is', $definition, $matches) !== 1) {
            return null;
        }

        $name = $matches[1];
        $rest = trim($matches[2]);
        $type = $this->type($rest);
        $length = null;
        $precision = null;
        $scale = null;
        if (preg_match('/^(\w+)\s*\(\s*(\d+)\s*(?:,\s*(\d+)\s*)?\)/i', $rest, $typeMatches) === 1) {
            $type = strtoupper($typeMatches[1]);
            if (isset($typeMatches[3])) {
                $precision = (int) $typeMatches[2];
                $scale = (int) $typeMatches[3];
            } else {
                $length = (int) $typeMatches[2];
            }
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
            autoIncrement: str_contains($upperRest, 'AUTOINCREMENT'),
            generated: preg_match('/\bAS\s*\(/i', $rest) === 1,
            enumValues: null,
        );
    }

    /**
     * Answers the type a declaration names.
     *
     * A column declared with no type has blob affinity, which is what SQLite
     * gives it, so that is what is reported.
     *
     * @param string $rest Everything after the column name
     *
     * @return string The type name in capitals, or BLOB when none was declared
     */
    public function type(string $rest): string
    {
        if ($rest !== '' && preg_match('/^(\w+)/i', $rest, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return 'BLOB';
    }

    /**
     * Answers the value a DEFAULT clause names, read as the type it is written as.
     *
     * A default is followed by whatever else the column declares, so the
     * clause has to end at the first keyword that could start one. A
     * parenthesized default is an expression the server evaluates, and is
     * carried through as written rather than read as a value.
     *
     * @param string $rest Everything after the column name
     *
     * @return int|float|string|bool|null The default, or null when none was declared
     */
    public function defaultValue(string $rest): int|float|string|bool|null
    {
        $pattern = '/\bDEFAULT\s+(.+?)'
            . '(?:\s+(?:NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|COLLATE|GENERATED|AS\s*\()|$)/is';
        if (preg_match($pattern, $rest, $matches) !== 1) {
            return null;
        }

        $written = trim((string) preg_replace(
            '/\s+(NOT\s+NULL|NULL|PRIMARY|UNIQUE|CHECK|REFERENCES|COLLATE).*$/i',
            '',
            trim($matches[1]),
        ));

        if (str_starts_with($written, '(') && str_ends_with($written, ')')) {
            return $written;
        }
        if (preg_match("/^['\"](.*)['\"]\s*$/s", $written, $stringMatches) === 1) {
            return $stringMatches[1];
        }
        if (strtoupper($written) === 'NULL') {
            return null;
        }
        if (strtoupper($written) === 'TRUE' || $written === '1') {
            return true;
        }
        if (strtoupper($written) === 'FALSE' || $written === '0') {
            return false;
        }
        if (is_numeric($written)) {
            return str_contains($written, '.') ? (float) $written : (int) $written;
        }

        return $written;
    }
}
