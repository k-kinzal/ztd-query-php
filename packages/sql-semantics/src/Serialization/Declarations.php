<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Column\AutoIncrementColumn;
use SqlSemantics\Schema\Constraint\PrimaryKey;
use SqlSemantics\Schema\Table;

/**
 * Writes schema declarations from their semantic fields.
 *
 * @visibility SqlSemantics
 */
final class Declarations
{
    /**
     * Preserves the template relation instead of expanding it into column declarations.
     */
    public static function like(\SqlSemantics\Model\Statement\Table\CreateTableLikeStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return new Tree('create-like', [Build::keyword('CREATE' . ($statement->temporary ? ' TEMPORARY' : '') . ' TABLE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Build::identifier($statement->target->parts, $dialect), Build::keyword('LIKE'), Query\Relations::target($statement->template, $dialect)]);
    }

    /**
     * Writes a table and its declared columns, integrity constraints and indexes.
     */
    public static function table(CreateTableStatement $statement): Tree
    {
        $table = $statement->definition->table;
        $dialect = $statement->origin->dialect;
        $columns = [];
        foreach ($table->columns as $position => $column) {
            array_push($columns, ...Definition\Table\PostgreSqlTables::templates($statement->templates, $position));
            $columns[] = Definition\Columns::write($column, $dialect);
        }
        array_push($columns, ...Definition\Table\PostgreSqlTables::templates($statement->templates, count($table->columns)));
        foreach ($table->constraints as $constraint) {
            if ($dialect === \SqlSemantics\Dialect::Sqlite && $constraint instanceof PrimaryKey && array_filter($table->columns, static fn ($column): bool => $column->generation instanceof AutoIncrementColumn) !== []) {
                continue;
            }
            $columns[] = Definition\Constraints::write($constraint, $dialect);
        }
        foreach ($table->indexes as $index) {
            $columns[] = Definition\Indexes::inline($index, $dialect);
        }
        foreach ($statement->exclusions as $exclusion) {
            $columns[] = Definition\Relation\ConstraintActions::exclusion($exclusion);
        }
        $properties = $table->properties;
        $modifier = $properties instanceof Table\PostgreSqlProperties ? match ($properties->persistence) {
            Table\Persistence::Permanent => '', Table\Persistence::Temporary => 'TEMPORARY ', Table\Persistence::Unlogged => 'UNLOGGED ',
        } : (($properties instanceof Table\MySqlProperties || $properties instanceof Table\SqliteProperties) && $properties->temporary ? 'TEMPORARY ' : '');
        return new Tree('create-table', [Build::keyword('CREATE ' . $modifier . 'TABLE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Build::identifier(self::tableName($table), $dialect), Build::parentheses(Build::separated($columns)), Definition\Storage::table($properties, $dialect)]);
    }

    /**
     * Writes a standalone index creation operation.
     */
    public static function index(CreateIndexStatement $statement): Tree
    {
        $definition = $statement->index->definition;
        $dialect = $statement->origin->dialect;
        $name = $definition->name === null ? [] : [Build::identifier($dialect === \SqlSemantics\Dialect::Sqlite && $definition->schema !== '' ? [$definition->schema, $definition->name] : [$definition->name], $dialect)];
        $target = $dialect === \SqlSemantics\Dialect::Sqlite ? Build::identifier([$statement->table->declaration->name], $dialect) : Query\Relations::target($statement->table, $dialect);
        if ($dialect === \SqlSemantics\Dialect::MySql) {
            return Definition\MySqlTable\IndexCreations::write($statement, $name, $target);
        }
        return new Tree('create-index', [Build::keyword('CREATE ' . Definition\Indexes::kind($definition) . 'INDEX' . ($statement->concurrently ? ' CONCURRENTLY' : '') . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), ...$name, Build::keyword('ON'), $target, ...($definition->method === null ? [] : [Build::keyword('USING'), Build::identifier([$definition->method], $dialect)]), Definition\Indexes::keys($definition, $dialect), Definition\Indexes::options($definition->properties, $dialect)]);
    }

    /**
     * Returns the written name of a declared table; a temporary table in the temporary schema is written unqualified.
     * @return non-empty-list<string>
     */
    public static function tableName(\SqlSemantics\Schema\TableDefinition $table): array
    {
        $properties = $table->properties;
        $temporary = $properties instanceof Table\PostgreSqlProperties && $properties->persistence === Table\Persistence::Temporary && $table->schema === 'pg_temp'
            || $properties instanceof Table\SqliteProperties && $properties->temporary && $table->schema === 'temp';
        return $table->schema === '' || $temporary ? [$table->name] : [$table->schema, $table->name];
    }
}
