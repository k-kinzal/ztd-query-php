<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql;

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\DataType;
use PhpMyAdmin\SqlParser\Components\OptionsArray;
use SqlFixture\Schema\ColumnDefinition;

/**
 * Reads one column definition from a parsed MySQL CREATE TABLE statement.
 *
 * MySQL says the same thing in more than one place, which is what makes this
 * a subject of its own. UNSIGNED may be recorded against the type or against
 * the column; a primary key may be declared beside the column or on its own
 * line; and the single number in parentheses is a precision for DECIMAL, a
 * width for BIT, and a length for everything else.
 */
final class MySqlColumnReader
{
    private const EXACT_NUMERIC_TYPES = ['DECIMAL', 'NUMERIC', 'DEC', 'FIXED'];

    /**
     * Reads one definition as a column.
     *
     * @param CreateDefinition $field Definition the parser produced
     * @param string $name Column name, with its backticks already removed
     * @param list<string> $primaryKeyColumns Columns the primary key is made of
     *
     * @return ColumnDefinition|null The column, or null when the definition declares no type
     */
    public function read(CreateDefinition $field, string $name, array $primaryKeyColumns): ?ColumnDefinition
    {
        $type = $field->type;
        if (!$type instanceof DataType || $type->name === null) {
            return null;
        }

        $typeName = strtoupper($type->name);
        $options = $field->options;
        $isPrimaryKey = in_array($name, $primaryKeyColumns, true)
            || ($options instanceof OptionsArray && $options->has('PRIMARY KEY') !== false);

        $length = null;
        $precision = null;
        $scale = null;
        $parameters = $type->parameters;
        if ($parameters !== []) {
            if (in_array($typeName, self::EXACT_NUMERIC_TYPES, true)) {
                $precision = isset($parameters[0]) ? (int) $parameters[0] : 10;
                $scale = isset($parameters[1]) ? (int) $parameters[1] : 0;
            } elseif ($typeName === 'BIT') {
                $length = isset($parameters[0]) ? (int) $parameters[0] : 1;
            } else {
                $length = isset($parameters[0]) ? (int) $parameters[0] : null;
            }
        }

        return new ColumnDefinition(
            name: $name,
            type: $typeName,
            length: $length,
            precision: $precision,
            scale: $scale,
            nullable: !$isPrimaryKey
                && !($options instanceof OptionsArray && $options->has('NOT NULL') !== false),
            unsigned: $type->options->has('UNSIGNED') !== false
                || ($options instanceof OptionsArray && $options->has('UNSIGNED') !== false),
            default: $this->defaultValue($options),
            autoIncrement: $options instanceof OptionsArray && $options->has('AUTO_INCREMENT') !== false,
            generated: $options instanceof OptionsArray
                && ($options->has('GENERATED') !== false || $options->has('AS') !== false),
            enumValues: in_array($typeName, ['ENUM', 'SET'], true) ? $this->enumMembers($parameters) : null,
        );
    }

    /**
     * Answers the value a DEFAULT clause names, read as the type it is written as.
     *
     * @param OptionsArray|null $options Options the parser recorded against the column
     *
     * @return mixed The default, or null when none was declared
     */
    public function defaultValue(?OptionsArray $options): mixed
    {
        if ($options === null || $options->has('DEFAULT') === false) {
            return null;
        }

        foreach ($options->options as $option) {
            if (!is_array($option) || ($option['name'] ?? null) !== 'DEFAULT') {
                continue;
            }

            $written = $option['value'] ?? null;
            if (!is_string($written)) {
                return $written;
            }
            if (preg_match('/^[\'"](.*)[\'"]\s*$/s', $written, $matches) === 1) {
                return $matches[1];
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

        return null;
    }

    /**
     * Answers the members an ENUM or SET declares, unquoted.
     *
     * @param array<mixed> $parameters Parameters the parser recorded against the type
     *
     * @return list<string> The members, in the order declared
     */
    public function enumMembers(array $parameters): array
    {
        $members = [];
        foreach ($parameters as $parameter) {
            if (is_string($parameter)) {
                $members[] = trim($parameter, '\'"');
            }
        }

        return $members;
    }
}
