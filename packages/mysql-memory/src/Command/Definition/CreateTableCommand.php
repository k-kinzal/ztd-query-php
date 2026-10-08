<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Key;
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
use MySqlMemory\Storage\Heap;
use Override;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE TABLE: declares the table in its database, with no rows, or with the rows of its query (TableQueries).
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
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates the table.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $create = $operation->statement;
        assert($create instanceof CreateTable);
        $session->transaction->commit();
        $schemaName = $create->name->schema->value ?? $session->variables->database;
        if ($schemaName === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($schemaName);
        if ($schema === null) {
            throw ErrorCode::BadDatabase->error($schemaName);
        }
        $name = $create->name->name->value;
        if ($schema->table($name) !== null || isset($schema->views[$name])) {
            if (!$create->ifNotExists) {
                throw ErrorCode::TableExists->error($name);
            }
            $context->note(ErrorCode::TableExists, $name);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        if ($create->query !== null) {
            return (new TableQueries($session, $context, $connection))->create($create, $operation, $schema);
        }
        $planner = new Planner($create, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $definition = (new Definitions($planner, $schema->collation))->table($create, $operation->declarations()[0], $schemaName);
        foreach ($this->duplicates($definition->keys) as $duplicate) {
            $context->warning(ErrorCode::DuplicateIndex, $duplicate->name, $schemaName . '.' . $name);
        }
        $schema->tables[$name] = new StoredTable($this->primaryNotNull($definition), new Heap());

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
            array_splice($columns, $position, 1, [new ColumnDefinition($column->name, $column->domain->withNullable(false), $column->default->declared && $column->default->value === null && $column->default->expression === null ? \MySqlMemory\Dictionary\Fill::none() : $column->default, $column->autoIncrement, $column->onUpdateNow, $column->generated, $column->invisible, $column->declaration, $column->comment)]);
        }

        return new TableDefinition($definition->schema, $definition->name, $columns, $definition->keys, $definition->declaration, $definition->engine, $definition->collation, $definition->temporary, $definition->comment, $definition->statement);
    }

    /**
     * Answers each key, other than the primary key, that indexes the same columns in the same way as a key before it.
     *
     * The server keeps such a key and warns that defining it is deprecated.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
     *
     * @param list<Key> $keys The keys in the order the table holds them
     * @return list<Key>
     */
    public function duplicates(array $keys): array
    {
        $duplicates = [];
        foreach ($keys as $position => $key) {
            foreach (array_slice($keys, 0, $position) as $earlier) {
                if ($key->kind !== KeyKind::Primary && $key->duplicates($earlier)) {
                    $duplicates[] = $key;
                    break;
                }
            }
        }

        return $duplicates;
    }

    /**
     * Tells whether a key kind refuses duplicates.
     */
    public function unique(KeyKind $kind): bool
    {
        return $kind === KeyKind::Primary || $kind === KeyKind::Unique;
    }
}
