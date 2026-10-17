<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Invalidates a protocol preparation when a referenced declaration changes.
 *
 * Ordinary executions consume the retained structure. A metadata invalidation
 * returns to the input boundary with the original request in the original
 * database, under current lexical and client settings. Black-box wire capture
 * on MySQL 8.4.7 confirms that this reinterpretation is server-side, without a
 * second COM_STMT_PREPARE request. It is separate from rebinding an existing
 * semantic object, which never reparses its request label.
 *
 * @visibility MySqlMemory
 */
final class PreparedCache
{
    /**
     * @param list<array{string, string, Table}> $tables Resolved schema, name and declaration identities
     */
    public function __construct(public PreparedStatement $definition, public array $tables)
    {
    }

    /**
     * Retains a definition and the declarations its named relations resolved to.
     */
    public static function capture(Operation $operation, Session $session): self
    {
        $tables = [];
        foreach ((new Walker())->find($operation->statement, NamedRelation::class) as $relation) {
            if (!$operation->facts->covers($relation)) {
                continue;
            }
            $resolution = $operation->facts->relation($relation)->table;
            if ($resolution instanceof DeclaredTable) {
                $table = $resolution->table;
                $tables[] = [$table->name->schema->value ?? $session->variables->database, $table->name->name->value, $table];
            }
        }
        return new self(PreparedStatement::capture($operation, $session), $tables);
    }

    /**
     * Tests declaration identities, ignoring data writes and unrelated table changes.
     */
    public function changed(Session $session): bool
    {
        $session->instance->dictionary->temporaries = $session->temporaries;
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $current = $session->instance->dictionary->declarations();
        foreach ($this->tables as [, , $table]) {
            if (!in_array($table, $current, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reprepares a stale protocol entry at the SQL input boundary.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The current bindings
     * @throws \MySqlMemory\Error\SqlError When the new interpretation fails
     */
    public function refresh(Session $session, string $sql, ParameterBindings $bindings, array $parameters): void
    {
        if (!$this->changed($session)) {
            return;
        }
        $database = $session->variables->database;
        $domains = $session->preparation->domains;
        $session->variables->database = $this->definition->database;
        $session->preparation->domains = [];
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_reprepare');
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_execute');
        try {
            $operation = $session->analyze($sql, true, $parameters);
            (new \MySqlMemory\Hint\Hints())->prepare($operation, $session);
            $replacement = self::capture($operation, $session);
            $this->definition = $replacement->definition;
            $this->tables = $replacement->tables;
            $bindings->types = ParameterBindings::capture($operation)->types;
        } finally {
            $session->variables->database = $database;
            $session->preparation->domains = $domains;
        }
    }
}
