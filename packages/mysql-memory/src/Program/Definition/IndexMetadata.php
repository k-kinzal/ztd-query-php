<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Definition;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use WeakMap;

/**
 * Tracks legacy stored index declarations across destination metadata changes.
 *
 * MySQL 5.6.51 and 5.7.44 count another CREATE attempt when a previously reached
 * instruction opens a new or altered destination. An absent destination does
 * not cause this retry. Repeated unchanged invocations count only once.
 * Observed through SQL command counters, without implementation-source access.
 *
 * @visibility MySqlMemory
 */
final class IndexMetadata
{
    /**
     * Records an observed retry without modifying or reconstructing the instruction.
     */
    public function refresh(CreateTable $definition, Session $session): void
    {
        if (!$session->settings()->legacy() || $session->program === null || !(new Roles())->elements($definition->elements)) {
            return;
        }
        $instruction = $session->program->instruction instanceof CreateTable ? $session->program->instruction : $definition;
        $cache = $session->preparation->indexCreations ??= new WeakMap();
        $current = $this->current($definition, $session);
        if (isset($cache[$instruction]) && $current !== null && $cache[$instruction][0] !== $current) {
            StatementCounters::command($session, 'Com_create_table');
        }
        $cache[$instruction] = [$current];
    }

    /**
     * Reads destination identity in the program's database and temporary-table scope.
     */
    public function current(CreateTable $definition, Session $session): TableDefinition|View|null
    {
        $database = $definition->name->schema->value ?? $session->variables->database;
        $name = $definition->name->name->value;
        if ($definition->temporary()) {
            return $session->temporaries->table($database, $name)?->definition;
        }
        $schema = $session->instance->dictionary->schema($database);
        return $schema?->table($name)->definition ?? $schema?->views[$name] ?? null;
    }
}
