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
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        if ($operation instanceof CreateTable && $operation->hasExecutionCondition()) {
            $rewrite = new Expression\GenerationRewrite((new \MySqlMemory\Command\Prepared\GenerationParameters())->values($operation, $connection->parameters));
            return (new \MySqlMemory\Program\Expression\TableUse($rewrite))->raise($operation, $session);
        }
        return $this->create($operation, $session, $context, $connection);
    }

    /**
     * Installs a complete request after its invocation conditions have been resolved.
     */
    public function create(MySqlStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $create = $operation;
        assert($create instanceof CreateTable);
        $started = (new \MySqlMemory\Session\Access\TableCreation())->requested($create);
        if ($create->temporaryWords === 0) {
            $session->transaction->commit();
        }
        $schema = $this->schema($create, $operation, $session, $connection);
        $schemaName = $schema->name;
        $name = $create->name->name->value;
        $planner = new Planner($create, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $definitions = new Definitions($planner, $schema->collation);
        $definition = $session->settings()->legacy() && $create->query === null ? $definitions->shape($create, $operation->declarations()[0], $schemaName) : null;
        if ($create->temporaryWords > 0 ? $session->temporaries->table($schemaName, $name) !== null : $schema->table($name) !== null || isset($schema->views[$name])) {
            if (!$create->ifNotExists) {
                throw SchemaError::TableExists->error($name);
            }
            $context->note(SchemaError::TableExists, $name);
            if ($started) {
                $session->transaction->creation->begin();
            }

            return new Completion(0, 0, $context->diagnostics->raised());
        }
        (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->rows($create);
        $this->columns($operation, $session, $connection);
        (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->forbidden($create);
        (new StorageOptions())->check($create, $session, $context);
        if ($create->temporaryWords > 0 && $session->transaction->open) {
            $session->transaction->temporaries['created'] = true;
        }
        if ($create->query !== null) {
            return (new TableQueries($session, $context, $connection))->create($create, $operation, $schema);
        }
        $definition = Partition\PartitionDefinitions::apply($planner, $create, $definition ?? $definitions->shape($create, $operation->declarations()[0], $schemaName));
        if (!$definition->temporary) {
            Constraints::unique($definition, $schema);
        }
        $this->store($definition, $session, $context, $started);

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Opens the destination schema after versioned definition and tablespace checks.
     * Legacy empty definitions fail before schema lookup. Modern tablespace lookup
     * precedes the existing-table shortcut, including IF NOT EXISTS.
     */
    public function schema(CreateTable $create, MySqlStatement $operation, Session $session, Connection $connection): \MySqlMemory\Dictionary\Schema
    {
        $schemaName = $create->name->schema->value ?? $session->variables->database;
        if ($schemaName === '') {
            throw QueryError::NoDatabase->error();
        }
        if ($session->settings()->legacy()) {
            $this->columns($operation, $session, $connection);
        }
        $schema = $session->instance->dictionary->schema($schemaName);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($schemaName);
        }
        if (!$session->settings()->legacy()) {
            $storage = new StorageOptions();
            foreach ($create->options as $option) {
                if ($option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption) {
                    $storage->tablespace($option, $create, $storage->engine($create, $session->variables), $session);
                }
            }
        }
        if (in_array($session->settings()->release(), [\SqlSemantics\Contract\GrammarRelease::MySql810, \SqlSemantics\Contract\GrammarRelease::MySql820, \SqlSemantics\Contract\GrammarRelease::MySql830, \SqlSemantics\Contract\GrammarRelease::MySql901, \SqlSemantics\Contract\GrammarRelease::MySql910], true)) {
            $this->columns($operation, $session, $connection);
        }
        (new Partition\PartitionNames())->check($create->partitioning);
        return $schema;
    }

    /**
     * Raises the semantic empty-definition condition at the release's preparation phase.
     */
    public function columns(MySqlStatement $operation, Session $session, Connection $connection): void
    {
        foreach ($operation->statementInformation()->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns) {
                if ($session->settings()->legacy()) {
                    throw SchemaError::TableMustHaveColumns->error();
                }
                $create = $operation;
                assert($create instanceof CreateTable);
                $schema = $session->instance->dictionary->schema($create->name->schema->value ?? $session->variables->database);
                \SqlSemantics\Diagnostic\Check::invariant($schema !== null, 'The destination schema precedes modern column validation.');
                $planner = new Planner($create, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
                (new Definitions($planner, $schema->collation))->table($create, $operation->declarations()[0], $schema->name);
            }
        }
    }

    /**
     * Stores a prepared definition immediately, or holds it until COMMIT when START TRANSACTION was requested.
     */
    public function store(TableDefinition $definition, Session $session, Context $context, bool $started): void
    {
        foreach ($this->duplicates($definition->keys, $definition->engine) as $duplicate) {
            $context->warning(SchemaError::DuplicateIndex, $duplicate->name, $definition->schema . '.' . $definition->name);
        }
        $table = new StoredTable($this->primaryNotNull($definition), new Heap(), created: self::created($context), clock: (int) floor($context->variables->clock()));
        if ($started) {
            $session->transaction->creation->begin($table);
        } else {
            $session->instance->dictionary->store($table, $session->temporaries);
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
    public function duplicates(array $keys, string $engine = 'InnoDB'): array
    {
        $duplicates = [];
        foreach ($keys as $position => $key) {
            foreach (array_slice($keys, 0, $position) as $earlier) {
                if ($key->kind !== KeyKind::Primary && $key->duplicates($earlier, $engine)) {
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
