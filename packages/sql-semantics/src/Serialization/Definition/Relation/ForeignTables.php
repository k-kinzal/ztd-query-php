<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation as Statement;
use SqlSemantics\Schema\Column\Attributes;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Serialization\Definition\Columns;
use SqlSemantics\Serialization\Definition\Constraints;
use SqlSemantics\Serialization\Definition\Foreign\WrapperOptions;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Writes foreign table declarations from their declared columns, server, and options.
 * @visibility SqlSemantics
 */
final class ForeignTables
{
    /**
     * Returns null for statements outside the foreign table family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        if ($statement instanceof Statement\CreateForeignPartitionStatement) {
            $elements = [...array_map(self::column(...), $statement->columns), ...array_map(static fn ($constraint): Tree => Constraints::write($constraint, $dialect), $statement->constraints)];
            return new Tree('create-foreign-partition', [
                Build::keyword('CREATE FOREIGN TABLE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Build::identifier($statement->name->parts, $dialect),
                Build::keyword('PARTITION OF'), Build::identifier($statement->parent->parts, $dialect),
                ...($elements === [] ? [] : [Build::parentheses(Build::separated($elements))]),
                PartitionActions::bound($statement->bound),
                ...self::server($statement->server, $statement->options),
            ]);
        }
        if (!$statement instanceof Statement\CreateForeignTableStatement) {
            return null;
        }
        $table = $statement->definition->table;
        $elements = [];
        foreach ($table->columns as $column) {
            $options = array_values(array_filter($statement->columnOptions, static fn (Foreign\ColumnForeignOptions $candidate): bool => $candidate->column === $column->name));
            $parts = Columns::write($column, $dialect)->children;
            $elements[] = new Tree('foreign-column', [...array_slice($parts, 0, 3), ...($options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated(array_map(WrapperOptions::option(...), $options[0]->options)))]), ...array_slice($parts, 3)]);
        }
        foreach ($table->constraints as $constraint) {
            $elements[] = Constraints::write($constraint, $dialect);
        }
        foreach ($statement->templates as $template) {
            $elements[] = new Tree('table-template', [Build::keyword('LIKE'), Build::identifier($template->source->parts, $dialect), ...array_map(static fn (Foreign\TemplateSelection $selection): Tree => Build::keyword(($selection->including ? 'INCLUDING ' : 'EXCLUDING ') . $selection->property->value), $template->selections)]);
        }
        return new Tree('create-foreign-table', [
            Build::keyword('CREATE FOREIGN TABLE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Build::identifier($table->schema === '' ? [$table->name] : [$table->schema, $table->name], $dialect),
            Build::parentheses(Build::separated($elements)),
            ...($statement->inherits === [] ? [] : [Build::keyword('INHERITS'), Build::parentheses(Build::separated(array_map(static fn ($parent): Tree => Build::identifier($parent->parts, $dialect), $statement->inherits)))]),
            ...self::server($statement->server, $statement->options),
        ]);
    }

    /**
     * @param list<\SqlSemantics\Model\Definition\Foreign\ForeignOption> $options
     * @return list<Tree>
     */
    public static function server(string $server, array $options): array
    {
        return [Build::keyword('SERVER'), Build::identifier([$server], Dialect::PostgreSql), ...($options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated(array_map(WrapperOptions::option(...), $options)))])];
    }

    /**
     * A partition column override is written like a column declaration without its type.
     */
    public static function column(Foreign\PartitionColumn $column): Tree
    {
        $dialect = Dialect::PostgreSql;
        $declaration = new ColumnDefinition($column->column, TypeDescriptor::builtin($dialect, 'integer'), $column->nullability, new \SqlParser\Parser\Node('partition_column', 0, []), $column->generation, new Attributes(collation: $column->collation));
        $parts = Columns::write($declaration, $dialect)->children;
        return new Tree('partition-column', [$parts[0], Build::keyword('WITH OPTIONS'), ...array_slice($parts, 2), ...array_map(static fn ($constraint): Tree => Constraints::column($constraint, $dialect), $column->constraints)]);
    }
}
