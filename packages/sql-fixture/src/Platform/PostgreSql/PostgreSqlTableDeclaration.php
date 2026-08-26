<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql;

/**
 * Writes a CREATE TABLE that says what the catalog described.
 *
 * The catalog answers in its own terms — a length in one column, a precision
 * in another, a type name that may be an alias or the name of a user-defined
 * type — and none of that is a declaration. Writing it back out as one means
 * a live table and a table read from a `.sql` file arrive at the same reader,
 * so there is one place where a declaration is understood rather than two.
 */
final class PostgreSqlTableDeclaration
{
    /**
     * Writes the statement that would declare the table the catalog described.
     *
     * @param string $table Table being declared
     * @param list<array{column_name: string, data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, is_nullable: string, column_default: ?string, udt_name: string}> $columns Columns as the catalog answered
     * @param list<string> $primaryKeys Columns the primary key is made of
     *
     * @return string A CREATE TABLE statement
     */
    public function of(string $table, array $columns, array $primaryKeys): string
    {
        $declarations = [];
        foreach ($columns as $column) {
            $declaration = '"' . $column['column_name'] . '" ' . $this->typeOf($column);
            if ($column['is_nullable'] === 'NO') {
                $declaration .= ' NOT NULL';
            }
            if ($column['column_default'] !== null) {
                $declaration .= ' DEFAULT ' . $column['column_default'];
            }
            $declarations[] = $declaration;
        }

        if ($primaryKeys !== []) {
            $quoted = array_map(static fn (string $key): string => '"' . $key . '"', $primaryKeys);
            $declarations[] = 'PRIMARY KEY (' . implode(', ', $quoted) . ')';
        }

        return "CREATE TABLE \"{$table}\" (" . implode(', ', $declarations) . ')';
    }

    /**
     * Writes the type of one column as it would have been declared.
     *
     * The catalog reports a length and a precision beside the type rather than
     * within it, so both are put back. An array or a user-defined type is
     * reported under a name of its own, which is the only name that would
     * declare it again.
     *
     * @param array{data_type: string, character_maximum_length: ?string, numeric_precision: ?string, numeric_scale: ?string, udt_name: string} $column Column as the catalog answered
     *
     * @return string The type, as a declaration spells it
     */
    public function typeOf(array $column): string
    {
        $type = strtoupper($column['data_type']);
        $length = $column['character_maximum_length'];
        $precision = $column['numeric_precision'];

        if ($type === 'CHARACTER VARYING' && $length !== null) {
            return "VARCHAR({$length})";
        }
        if ($type === 'CHARACTER' && $length !== null) {
            return "CHAR({$length})";
        }
        if ($type === 'NUMERIC' && $precision !== null) {
            $scale = $column['numeric_scale'];

            return $scale !== null && $scale !== '0' ? "NUMERIC({$precision}, {$scale})" : "NUMERIC({$precision})";
        }
        if ($type === 'ARRAY' || $type === 'USER-DEFINED') {
            return strtoupper($column['udt_name']);
        }

        return $type;
    }
}
