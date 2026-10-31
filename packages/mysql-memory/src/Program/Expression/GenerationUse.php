<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

/**
 * Invokes a retained legacy generation declaration after destination lookup.
 * Existing tables can satisfy IF NOT EXISTS without evaluating the declaration.
 * Verified through SQL on MySQL 5.7.44.
 *
 * @visibility MySqlMemory
 */
final class GenerationUse
{
    /**
     * @param \MySqlMemory\Command\Definition\Expression\GenerationRewrite $rewrite The current execution's structural substitutions
     */
    public function __construct(public readonly \MySqlMemory\Command\Definition\Expression\GenerationRewrite $rewrite = new \MySqlMemory\Command\Definition\Expression\GenerationRewrite())
    {
    }

    /**
     * Checks the destination before reporting the declaration's invocation condition.
     *
     * @throws SqlError When a destination or generation declaration is rejected
     */
    public function execute(CreateTable $definition, Session $session, ?string $database = null): \MySqlMemory\Result\Reply
    {
        (new \MySqlMemory\Command\Definition\Expression\GenerationExecution())->variables($definition, $session);
        $database = $definition->name->schema->value ?? $database ?? $session->variables->database;
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        if (!$definition->temporary()) {
            $session->transaction->commit();
        }
        $name = $definition->name->name->value;
        $exists = $definition->temporary() ? $session->temporaries->table($database, $name) !== null : $schema->table($name) !== null || isset($schema->views[$name]);
        if ($exists) {
            if (!$definition->ifNotExists) {
                throw SchemaError::TableExists->error($name);
            }
            $session->diagnostics->note(SchemaError::TableExists, SchemaError::TableExists->message($name));
            $session->variables->rowCount = 0;
            return new Completion(warnings: $session->diagnostics->raised());
        }
        return $this->create($definition, $session, $database);
    }

    /**
     * Rewrites successful legacy expressions and preserves every remaining condition.
     */
    public function create(CreateTable $source, Session $session, string $database): \MySqlMemory\Result\Reply
    {
        $definition = $this->rewrite->create($source);
        $name = $definition->name->name->value;
        $columns = array_values(array_filter($definition->elements, static fn ($element): bool => $element instanceof ColumnDefinition));
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($columns)) {
            throw (new \MySqlMemory\Command\Definition\Expression\GenerationCondition())->columns($columns, $database, $name, $definition->temporary(), (new TableUse())->bound($definition, $session)->statementInformation(), $session);
        }
        $execution = new \MySqlMemory\Command\Definition\Expression\GenerationExecution();
        $reply = $execution->run($definition, $session, $database);
        $table = $definition->temporary() ? $session->temporaries->table($database, $name) : $session->instance->dictionary->schema($database)?->table($name);
        if ($table !== null) {
            $execution->display($table, $source, $session, $this->rewrite->parameters);
        }
        return $reply;
    }
}
