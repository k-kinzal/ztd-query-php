<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Definition;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use WeakMap;

/**
 * Reaches a retained temporary-storage conflict after opening source tables and the database.
 *
 * Neither existing destinations nor IF NOT EXISTS bypass the conflict. Column and tablespace preparation follow it; an enforced foreign key
 * with a creation query is refused before source preparation. PREPARE and stored routine
 * creation retain the instruction; invocation checks its current dependencies.
 * Observed through SQL on MySQL 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class TemporaryStorage
{
    /**
     * Repeats syntax notices when a stored conflicting definition is prepared again.
     * First invocation consumes the load notices. Routine DDL invalidates this cache.
     */
    public function start(CreateTable $definition, Session $session): void
    {
        if ($session->program === null || !(new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation\CreationRoles())->temporaryStorage($definition->temporary(), $definition->options)) {
            return;
        }
        $instruction = $session->program->instruction instanceof CreateTable ? $session->program->instruction : $definition;
        $cache = $session->preparation->storageCreations ??= new WeakMap();
        $seen = isset($cache[$instruction]);
        $cache[$instruction] = true;
        if (!$seen) {
            return;
        }
        $facts = (new \MySqlMemory\Program\Expression\TableUse())->bound($definition, $session)->statementInformation();
        foreach ($facts->warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation && $warning->phase() === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
    }

    /**
     * Resolves earlier dependencies without fabricating an ordinary creation statement.
     *
     * @throws SqlError When a dependency or the retained AUTOEXTEND_SIZE condition is reached
     */
    public function raise(CreateTable $definition, Session $session, ?string $preparationDatabase = null): never
    {
        if ($definition->query !== null && array_filter($definition->elements, static fn ($element): bool => (new \MySqlMemory\Command\Definition\Constraint\ColumnReferences())->key($element, $session->settings()->release()) !== null) !== []) {
            throw \MySqlMemory\Error\Family\TransactionError::CreateTransactionForeignKey->error();
        }
        $saved = $session->variables->database;
        $session->variables->database = $preparationDatabase ?? $saved;
        try {
            if ($definition->query !== null) {
                $query = (new CreationQueryUse())->bound($definition, $session);
                (new Problems())->raise($query, $session);
            }
            $database = $definition->name->schema->value ?? $session->variables->database;
            if ($session->instance->dictionary->schema($database) === null) {
                throw QueryError::BadDatabase->error($database);
            }
            throw SchemaError::TemporaryAutoextend->error();
        } finally {
            $session->variables->database = $saved;
        }
    }
}
