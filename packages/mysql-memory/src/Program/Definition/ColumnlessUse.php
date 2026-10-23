<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Definition;

use MySqlMemory\Command\Definition\Constraint\CheckDefinitions;
use MySqlMemory\Command\Definition\Definitions;
use MySqlMemory\Command\Definition\Partition\PartitionNames;
use MySqlMemory\Command\Definition\StorageOptions;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Invokes a complete columnless creation without fabricating an ordinary declaration.
 *
 * Legacy releases require columns before opening the schema. MySQL 8.0 and 8.4
 * can complete IF NOT EXISTS using the catalog; 8.1–8.3 and 9.x check the column
 * condition first. CHECK expression restrictions precede that shortcut.
 * Observed through SQL on MySQL 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class ColumnlessUse
{
    /**
     * Resolves the retained instruction against the destination catalog at invocation.
     *
     * @throws SqlError When the schema, constraints or columnless declaration are refused
     */
    public function execute(DeferredCreateTable $definition, Session $session, ?string $database = null): Completion
    {
        $database = $definition->name->schema->value ?? $database ?? $session->variables->database;
        if (!$definition->temporary()) {
            $session->transaction->commit();
        }
        (new PartitionNames())->required($definition->partitioning, $session->settings()->legacy());
        if ($session->settings()->legacy()) {
            throw SchemaError::TableMustHaveColumns->error();
        }
        $schema = $this->schema($definition, $database, $session);
        CheckDefinitions::forbidden($definition, false, release: $session->settings()->release());
        if (in_array($session->settings()->release(), [GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql901, GrammarRelease::MySql910], true)) {
            $this->columns($definition, $schema, $session);
        }
        (new PartitionNames())->check($definition->partitioning);
        $name = $definition->name->name->value;
        $exists = $definition->temporary() ? $session->temporaries->table($database, $name) !== null : $schema->table($name) !== null || isset($schema->views[$name]);
        if ($exists) {
            if (!$definition->ifNotExists) {
                throw SchemaError::TableExists->error($name);
            }
            $session->diagnostics->note(SchemaError::TableExists, SchemaError::TableExists->message($name));
            if ((new \MySqlMemory\Session\Access\TableCreation())->requested($definition)) {
                $session->transaction->creation->begin();
            }
            $session->variables->rowCount = 0;
            return new Completion(warnings: $session->diagnostics->raised());
        }
        $this->columns($definition, $schema, $session);
        throw SchemaError::NoVisibleColumn->error();
    }

    /**
     * Opens the destination and its retained tablespace before the existing-table check.
     *
     * @throws SqlError When the schema or tablespace is unavailable
     */
    public function schema(DeferredCreateTable $definition, string $database, Session $session): Schema
    {
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        $storage = new StorageOptions();
        foreach ($definition->options as $option) {
            if ($option instanceof TablespaceOption) {
                $storage->tablespace($option, $definition, $storage->engine($definition, $session->variables), $session);
            }
        }
        return $schema;
    }

    /**
     * Prepares the retained constraints and indexes using the semantic declaration and facts.
     *
     * @throws SqlError When a key, constraint or the missing visible columns are refused
     */
    public function columns(DeferredCreateTable $definition, Schema $schema, Session $session): void
    {
        $derivation = new Derivation($session->semantics()->context($session->instance->dictionary->declarations(), true, new SearchPath($schema->name), $session->resolution()));
        $definition->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $facts = $derivation->facts();
        $table = $facts->relation($definition)->table;
        Check::invariant($table instanceof DeclaredTable, 'A retained table instruction supplies its declaration.');
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $planner = new Planner($definition, $facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $builder = new Definitions($planner, $schema->collation);
        $engine = (new StorageOptions())->engine($definition, $session->variables);
        $builder->parts($definition, $table->table, $schema->name, $engine, $builder->collation($definition));
    }
}
