<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn;

/**
 * Tracks generation-template preparation across destination changes.
 *
 * Black-box SQL histories distinguish an original preparation from one refreshed
 * against a newly created destination. Dropping that refreshed destination can
 * disconnect the entire native instance. The emulator closes its sessions through
 * its lifecycle API; PHP continues normally. Grouped expressions remain live in
 * the binary protocol. MySQL 5.7 and 9.0 count an extra execution on refresh;
 * 8.0, 8.4 and 9.1 also refresh a removed destination without that extra count. No server implementation is consulted.
 *
 * @visibility MySqlMemory
 */
final class GenerationMetadata
{
    /**
     * Whether execution has refreshed against a different existing destination.
     */
    public bool $refreshed = false;

    /**
     * Whether this cache belongs to SQL PREPARE rather than the binary protocol.
     */
    public bool $sqlPrepared = false;

    /**
     * @param TableDefinition|View|null $destination The destination identity at preparation
     */
    public function __construct(public readonly DeferredCreateTable $definition, public readonly string $database, public TableDefinition|View|null $destination)
    {
    }

    /**
     * Captures complete deferred generation declarations for destination revalidation.
     */
    public static function capture(DeferredCreateTable $definition, string $database, Session $session): ?self
    {
        if (!(new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($definition->elements)) {
            return null;
        }
        $cache = new self($definition, $definition->name->schema->value ?? $database, null);
        $cache->destination = $cache->current($session);
        return $cache;
    }

    /**
     * Reads the destination identity without parsing the prepared request label.
     */
    public function current(Session $session): TableDefinition|View|null
    {
        $name = $this->definition->name->name->value;
        if ($this->definition->temporary()) {
            return $session->temporaries->table($this->database, $name)?->definition;
        }
        $schema = $session->instance->dictionary->schema($this->database);
        return $schema?->table($name)->definition ?? $schema?->views[$name] ?? null;
    }

    /**
     * Preserves reprepare counters and the observed instance-disconnect transition.
     *
     * @throws \MySqlMemory\Error\SqlError When the native transition disconnects sessions
     */
    public function refresh(Session $session): void
    {
        $legacy = $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744;
        $repeatedExecution = $legacy || $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql901;
        if ($legacy && ($this->refreshed || $this->destination !== null) && $session->instance->dictionary->schema($this->database) === null) {
            $unknown = \MySqlMemory\Error\Family\QueryError::BadDatabase;
            $session->diagnostics->note($unknown, $unknown->message($this->database));
            if ($this->refreshed) {
                throw new \MySqlMemory\Error\SqlError(\MySqlMemory\Error\Family\StatementError::UnknownError, 'Memory capacity exceeded (capacity 4096 bytes)', following: [[1049, $unknown->message($this->database)]], number: 34);
            }
        }
        $current = $this->current($session);
        if ($current === $this->destination) {
            return;
        }
        if ($current !== null || !$repeatedExecution) {
            foreach ($repeatedExecution ? ['Com_stmt_reprepare', 'Com_stmt_prepare', 'Com_stmt_execute'] : ['Com_stmt_reprepare', 'Com_stmt_prepare'] as $counter) {
                StatementCounters::command($session, $counter);
            }
            $this->destination = $current;
            $this->refreshed = true;
        } elseif ($legacy && $this->refreshed && $this->disconnects() && $session->instance->dictionary->schema($this->database) !== null) {
            $session->instance->shutdown();
            throw TransactionError::ServerGone->error();
        }
    }

    /**
     * A bare system variable alone retains its file diagnostic after invalidation.
     * Binary-protocol grouped operands remain live after destination invalidation;
     * SQL PREPARE retains the observed legacy disconnect.
     * Other retained legacy operands reproduce the independently observed disconnect.
     */
    public function disconnects(): bool
    {
        foreach ($this->definition->elements as $column) {
            if (!$column instanceof ColumnDefinition || !$column->specification instanceof DeferredGeneratedColumn) {
                continue;
            }
            $expression = $column->specification->expression;
            while ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
                $expression = $expression->operand;
            }
            if (!$expression instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable && ($this->sqlPrepared || !(new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\GroupedOperands())->deferred($expression))) {
                return true;
            }
        }
        return false;
    }
}
