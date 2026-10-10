<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Definition\Constraint\Constraints;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
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
        $started = (new \MySqlMemory\Session\Access\TableCreation())->requested($create);
        if ($create->temporaryWords === 0) {
            $session->transaction->commit();
        }
        $schemaName = $create->name->schema->value ?? $session->variables->database;
        if ($schemaName === '') {
            throw QueryError::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($schemaName);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($schemaName);
        }
        $name = $create->name->name->value;
        if ($create->temporaryWords > 0 ? $session->temporaries->table($schemaName, $name) !== null : $schema->table($name) !== null || isset($schema->views[$name])) {
            if (!$create->ifNotExists) {
                throw SchemaError::TableExists->error($name);
            }
            $context->note(SchemaError::TableExists, $name);
            if ($started) {
                $session->transaction->creation->begin();
            }

            return new Completion(0, 0, $context->diagnostics->count());
        }
        (new StorageOptions())->check($create, $session, $context);
        if ($create->temporaryWords > 0 && $session->transaction->open) {
            $session->transaction->temporaries['created'] = true;
        }
        if ($create->query !== null) {
            return (new TableQueries($session, $context, $connection))->create($create, $operation, $schema);
        }
        $planner = new Planner($create, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $definition = (new Definitions($planner, $schema->collation))->table($create, $operation->declarations()[0], $schemaName);
        if (!$definition->temporary) {
            Constraints::unique($definition, $schema);
        }
        $this->store($definition, $session, $context, $started);

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Stores a prepared definition immediately, or holds it until COMMIT when START TRANSACTION was requested.
     */
    public function store(TableDefinition $definition, Session $session, Context $context, bool $started): void
    {
        foreach ($this->duplicates($definition->keys) as $duplicate) {
            $context->warning(SchemaError::DuplicateIndex, $duplicate->name, $definition->schema . '.' . $definition->name);
        }
        $table = new StoredTable($this->primaryNotNull($definition), new Heap(), created: self::created($context), clock: (int) floor($context->variables->clock()));
        if ($started) {
            $session->transaction->creation->begin($table);
        } else {
            $session->instance->dictionary->store($table);
        }
    }

    /**
     * Answers the DDL creation clock: the file clock in MySQL 5.x, the statement clock in the transactional data dictionary.
     */
    public static function created(Context $context): int
    {
        return in_array($context->modes->release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true) ? (int) floor($context->variables->clock()) : (int) floor($context->started);
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
            array_splice($columns, $position, 1, [$column->withDomain($column->domain->withNullable(false))->withDefault($column->default->declared && $column->default->value === null && $column->default->expression === null ? \MySqlMemory\Dictionary\Fill::none() : $column->default)]);
        }

        return $definition->withColumns($columns);
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
