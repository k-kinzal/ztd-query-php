<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Reads a column declaration into a schema value.
 *
 * @visibility root
 */
final class ColumnParser
{
    /**
     * @param list<string> $primaryKeyColumns
     */
    public function parseColumnDefinition(
        CreateDefinition $field,
        string $columnName,
        array $primaryKeyColumns,
    ): ?ColumnDefinition {
        $type = $field->type;
        if (!$type instanceof DataType || $type->name === null) {
            return null;
        }

        $typeName = strtoupper($type->name);
        $options = $field->options;

        $nullable = !($options instanceof OptionsArray && ($options->has('NOT NULL') !== false || $options->has('PRIMARY KEY') !== false))
            && !in_array($columnName, $primaryKeyColumns, true);
        $unsigned = ($options instanceof OptionsArray && $options->has('UNSIGNED') !== false)
            || $type->options->has('UNSIGNED') !== false;
        $autoIncrement = $options instanceof OptionsArray && $options->has('AUTO_INCREMENT') !== false;
        $generated = $options instanceof OptionsArray && ($options->has('GENERATED') !== false || $options->has('AS') !== false);

        $shape = (new TypeParameters())->parse($type);
        $parameters = $type->parameters;

        $default = (new DefaultExpression())->extractDefault($options);

        $enumValues = null;
        if ($typeName === 'ENUM' || $typeName === 'SET') {
            $enumValues = (new TypeParameters())->extractEnumValues($parameters);
        }

        return new ColumnDefinition(
            name: $columnName,
            type: $typeName,
            length: $shape->length,
            precision: $shape->precision,
            scale: $shape->scale,
            nullable: $nullable,
            unsigned: $unsigned,
            default: $default,
            autoIncrement: $autoIncrement,
            generated: $generated,
            enumValues: $enumValues,
        );
    }
}
