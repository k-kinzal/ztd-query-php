<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Syntax\NodeReader;
use SqlParser\Parser\Node;

/**
 * Reads a column_def node into a schema column.
 *
 * @visibility root
 */
final class ColumnParser
{
    /**
     * Returns the column the node declares, or null when it names no typed column.
     *
     * @param list<string> $primaryKeyColumns
     */
    public function parseColumnDefinition(Node $columnDef, string $sql, array $primaryKeyColumns): ?ColumnDefinition
    {
        $reader = new NodeReader();
        $ident = $reader->child($columnDef, 'ident');
        $fieldDef = $reader->child($columnDef, 'field_def');
        $type = $fieldDef === null ? null : $reader->child($fieldDef, 'type');
        $name = $ident === null ? null : (new Identifier())->decode($ident);
        if ($name === null || $name === '' || $fieldDef === null || $type === null) {
            return null;
        }

        $attributes = (new ColumnAttributes())->read($fieldDef);
        $shape = (new TypeParameters())->parse($type);
        $autoIncrement = $attributes->autoIncrement || $shape->autoIncrement;
        $nullable = $attributes->nullable && !$shape->autoIncrement && !in_array($name, $primaryKeyColumns, true);
        $default = $attributes->default === null ? null : (new DefaultExpression())->extractDefault($attributes->default, $sql);
        $enumValues = in_array($shape->type, ['ENUM', 'SET'], true) ? (new TypeParameters())->extractEnumValues($type) : null;

        return new ColumnDefinition(
            name: $name,
            type: $shape->type,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $nullable,
            unsigned: $reader->containsToken($type, 'UNSIGNED_SYM') || $shape->autoIncrement,
            default: $default,
            autoIncrement: $autoIncrement,
            generated: $reader->token($fieldDef, 'AS') !== null,
            enumValues: $enumValues,
        );
    }
}
