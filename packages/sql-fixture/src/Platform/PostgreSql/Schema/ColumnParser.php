<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Schema\ColumnDefinition;

/**
 * Reads a column declaration into a schema value.
 *
 * @visibility root
 */
final class ColumnParser
{
    /**
     * @param list<string> $tablePrimaryKeys
     */
    public function parseColumnDefinition(string $definition, array $tablePrimaryKeys): ?ColumnDefinition
    {
        if (preg_match('/^"?(\w+)"?\s*(.*)/is', $definition, $matches) !== 1) {
            return null;
        }

        $columnName = $matches[1];
        $rest = trim($matches[2]);

        $shape = (new TypeDeclaration())->parse($rest);
        $type = $shape->type;
        $autoIncrement = $shape->autoIncrement;

        $upperRest = strtoupper($rest);
        $nullable = !str_contains($upperRest, 'NOT NULL');

        $isPrimaryKey = str_contains($upperRest, 'PRIMARY KEY') || in_array($columnName, $tablePrimaryKeys, true);
        if ($isPrimaryKey) {
            $nullable = false;
        }

        $default = (new DefaultExpression())->extractDefault($rest);

        $generated = preg_match('/\bGENERATED\s+/i', $rest) === 1;

        return new ColumnDefinition(
            name: $columnName,
            type: $type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $nullable,
            unsigned: false,
            default: $default,
            autoIncrement: $autoIncrement,
            generated: $generated,
            enumValues: null,
        );
    }
}
