<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

/**
 * Reads the parts of a PostgreSQL CREATE TABLE statement.
 *
 * This answers questions about the statement as a whole — what the table is
 * called, which text declares its columns, where one declaration ends and the
 * next begins, and which of them declare a constraint rather than a column.
 * What any single declaration means is a separate question.
 *
 * Splitting on commas has to respect nesting, because a CHECK constraint or a
 * NUMERIC precision puts commas inside parentheses that are part of one
 * declaration rather than between two.
 */
final class PostgreSqlCreateTable
{
    /**
     * Answers the statement with its comments gone and its whitespace collapsed.
     *
     * @param string $sql Statement as it was written
     *
     * @return string The same statement on one line
     */
    public function normalized(string $sql): string
    {
        $withoutLineComments = preg_replace('/--.*$/m', '', $sql);
        $withoutComments = (string) preg_replace('/\/\*.*?\*\//s', '', (string) $withoutLineComments);

        return preg_replace('/\s+/', ' ', trim($withoutComments)) ?? '';
    }

    /**
     * Answers the name of the table the statement creates.
     *
     * PostgreSQL qualifies a table by schema, and a fixture asks for it by its
     * own name, so the schema is dropped.
     *
     * @param string $sql Normalized statement
     *
     * @return string|null The table name, or null when the statement declares none
     */
    public function tableName(string $sql): ?string
    {
        $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"?(\w+)"?\.)?"?(\w+)"?\s*\(/i';

        return preg_match($pattern, $sql, $matches) === 1 ? $matches[2] : null;
    }

    /**
     * Answers the text between the outermost parentheses, where the columns are declared.
     *
     * @param string $sql Normalized statement
     *
     * @return string|null The declarations, or null when the statement has no parenthesized body
     */
    public function columnsBlock(string $sql): ?string
    {
        $start = strpos($sql, '(');
        $end = strrpos($sql, ')');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($sql, $start + 1, $end - $start - 1);
    }

    /**
     * Splits the declarations, treating a comma inside parentheses as part of one.
     *
     * @param string $columnsBlock Text between the outermost parentheses
     *
     * @return list<string> One declaration per entry, trimmed
     */
    public function definitions(string $columnsBlock): array
    {
        $definitions = [];
        $current = '';
        $depth = 0;

        for ($index = 0; $index < strlen($columnsBlock); $index++) {
            $character = $columnsBlock[$index];
            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;
            } elseif ($character === ',' && $depth === 0) {
                $definitions[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $character;
        }
        if (trim($current) !== '') {
            $definitions[] = trim($current);
        }

        return $definitions;
    }

    /**
     * Reports whether a declaration constrains the table rather than declaring a column.
     *
     * EXCLUDE is PostgreSQL's own, and is a table constraint like the rest.
     *
     * @param string $definition One declaration
     *
     * @return bool True when it is a constraint
     */
    public function isTableConstraint(string $definition): bool
    {
        $pattern = '/^(PRIMARY\s+KEY|FOREIGN\s+KEY|UNIQUE|CHECK|CONSTRAINT|EXCLUDE)\b/i';

        return preg_match($pattern, trim($definition)) === 1;
    }

    /**
     * Answers the columns a table-level PRIMARY KEY names.
     *
     * @param string $columnsBlock Text between the outermost parentheses
     *
     * @return list<string> Column names the key is made of, in the order declared
     */
    public function primaryKeys(string $columnsBlock): array
    {
        if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $columnsBlock, $matches) !== 1) {
            return [];
        }

        $primaryKeys = [];
        foreach (explode(',', $matches[1]) as $column) {
            $column = trim(trim($column), '"');
            if ($column !== '') {
                $primaryKeys[] = $column;
            }
        }

        return $primaryKeys;
    }
}
