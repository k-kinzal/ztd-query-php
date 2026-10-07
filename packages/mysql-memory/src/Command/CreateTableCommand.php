<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\TableData;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE TABLE: declares the table in its database, with no rows.
 *
 * The statement commits the open transaction. CREATE TABLE IF NOT EXISTS of an existing table
 * succeeds with a note.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility MySqlMemory
 */
final class CreateTableCommand implements Command
{
    /**
     * Answers true.
     */
    #[\Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates the table.
     */
    #[\Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $create = $operation->statement;
        assert($create instanceof CreateTable);
        $session->transaction->commit();
        $schemaName = $create->name->schema?->value ?? $session->variables->database;
        if ($schemaName === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($schemaName);
        if ($schema === null) {
            throw ErrorCode::BadDatabase->error($schemaName);
        }
        $name = $create->name->name->value;
        if ($schema->table($name) !== null) {
            if (!$create->ifNotExists) {
                throw ErrorCode::TableExists->error($name);
            }
            $context->note(ErrorCode::TableExists, $name);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        if ($create->query !== null) {
            throw ErrorCode::NotSupportedYet->error('CREATE TABLE ... SELECT');
        }
        $planner = new Planner($create, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $definition = (new Definitions($planner, $schema->collation))->table($create, $operation->declarations()[0], $schemaName);
        $schema->tables[$name] = new StoredTable($this->primaryNotNull($definition), new TableData());

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Makes the columns of the primary key NOT NULL.
     */
    public function primaryNotNull(TableDefinition $definition): TableDefinition
    {
        $primary = $definition->primaryKey();
        if ($primary === null) {
            return $definition;
        }
        $columns = $definition->columns;
        foreach ($primary->columns as $position) {
            $column = $columns[$position];
            $columns[$position] = new ColumnDefinition($column->name, $column->domain->withNullable(false), $column->default->declared && $column->default->value === null && $column->default->expression === null ? \MySqlMemory\Dictionary\ColumnDefault::none() : $column->default, $column->autoIncrement, $column->onUpdateNow, $column->generated, $column->invisible, $column->declaration, $column->comment);
        }

        return new TableDefinition($definition->schema, $definition->name, $columns, $definition->keys, $definition->declaration, $definition->engine, $definition->collation, $definition->temporary, $definition->comment);
    }

    /**
     * Tells whether a key kind refuses duplicates.
     */
    public function unique(KeyKind $kind): bool
    {
        return $kind === KeyKind::Primary || $kind === KeyKind::Unique;
    }
}
