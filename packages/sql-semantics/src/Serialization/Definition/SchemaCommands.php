<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition as Statement;
use SqlSemantics\Schema\Table;
use SqlSemantics\Serialization\Query\Queries;

/**
 * Writes named schema operations using the operands of each concrete statement.
 *
 * @visibility SqlSemantics
 */
final class SchemaCommands
{
    /**
     * Writes distinct declaration and alteration forms.
     */
    public static function write(Statement\DropTableStatement|Statement\DropViewStatement|Statement\DropIndexStatement|Statement\DropTriggerStatement|Statement\RenameTableStatement|Statement\RenameColumnStatement|Statement\DropColumnStatement|Statement\AddColumnStatement|Statement\CreateViewStatement|Statement\CreateTableAsStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return match (true) {
            $statement instanceof Statement\DropTableStatement => self::drop('TABLE', $statement->names, $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\DropViewStatement => self::drop('VIEW', $statement->names, $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\DropIndexStatement => self::drop('INDEX', $statement->names, $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\DropTriggerStatement => self::drop('TRIGGER', $statement->names, $statement->ifExists, $statement->behavior, $dialect),
            $statement instanceof Statement\RenameTableStatement => new Tree('rename-table', [Build::keyword('ALTER TABLE'), Build::identifier($statement->table->parts, $dialect), Build::keyword('RENAME TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\RenameColumnStatement => new Tree('rename-column', [Build::keyword('ALTER TABLE'), Build::identifier($statement->table->parts, $dialect), Build::keyword('RENAME COLUMN'), Build::identifier([$statement->column], $dialect), Build::keyword('TO'), Build::identifier([$statement->newName], $dialect)]),
            $statement instanceof Statement\DropColumnStatement => new Tree('drop-column', [Build::keyword('ALTER TABLE'), Build::identifier($statement->table->parts, $dialect), Build::keyword('DROP COLUMN' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier([$statement->column], $dialect), Build::keyword($statement->behavior->value)]),
            $statement instanceof Statement\AddColumnStatement => self::addColumn($statement),
            $statement instanceof Statement\CreateViewStatement => self::view($statement),
            $statement instanceof Statement\CreateTableAsStatement => self::tableAs($statement),
        };
    }

    /**
     * @param non-empty-list<\SqlSemantics\Model\Relation\QualifiedName> $names
     */
    public static function drop(string $kind, array $names, bool $ifExists, \SqlSemantics\Model\Definition\DropBehavior $behavior, Dialect $dialect): Tree
    {
        return new Tree('drop', [Build::keyword('DROP ' . $kind . ($ifExists ? ' IF EXISTS' : '')), Build::separated(array_map(static fn ($name): Tree => Build::identifier($name->parts, $dialect), $names)), Build::keyword($behavior->value)]);
    }

    /**
     * Writes a new column and its local constraints.
     */
    public static function addColumn(Statement\AddColumnStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return new Tree('add-column', [Build::keyword('ALTER TABLE'), Build::identifier($statement->table->parts, $dialect), Build::keyword('ADD COLUMN' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Columns::write($statement->column, $dialect), ...array_map(static fn ($constraint): Tree => Constraints::column($constraint, $dialect), $statement->constraints)]);
    }

    /**
     * Writes the view's query and declared output aliases.
     */
    public static function view(Statement\CreateViewStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return new Tree('create-view', [Build::keyword('CREATE' . ($statement->replace ? ' OR REPLACE' : '') . ($statement->temporary ? ' TEMPORARY' : '') . ' VIEW' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Build::identifier($statement->name->parts, $dialect), ...($statement->columns === [] ? [] : [Constraints::columns($statement->columns, $dialect)]), Build::keyword('AS'), Queries::write($statement->query), ...($statement->check === ViewCheck::None ? [] : [Build::keyword('WITH ' . $statement->check->value . ' CHECK OPTION')])]);
    }

    /**
     * Writes a table whose columns originate from an input query.
     */
    public static function tableAs(Statement\CreateTableAsStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        $properties = $statement->properties;
        $modifier = $properties instanceof Table\PostgreSqlProperties ? match ($properties->persistence) {
            Table\Persistence::Permanent => '', Table\Persistence::Temporary => 'TEMPORARY ', Table\Persistence::Unlogged => 'UNLOGGED ',
        } : (($properties instanceof Table\MySqlProperties || $properties instanceof Table\SqliteProperties) && $properties->temporary ? 'TEMPORARY ' : '');
        return new Tree('create-table-as', [Build::keyword('CREATE ' . $modifier . 'TABLE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')), Build::identifier($statement->name->parts, $dialect), ...($statement->columns === [] ? [] : [Constraints::columns($statement->columns, $dialect)]), Storage::table($properties, $dialect), Build::keyword('AS'), Queries::write($statement->query), ...($statement->withData ? [] : [Build::keyword('WITH NO DATA')])]);
    }
}
