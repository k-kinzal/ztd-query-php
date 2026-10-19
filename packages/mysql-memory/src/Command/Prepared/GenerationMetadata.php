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
 * Tracks native 5.7 generation-template behavior across destination changes.
 *
 * Black-box SQL histories distinguish an original preparation from one refreshed
 * against a newly created destination. Dropping that refreshed destination can
 * disconnect the entire native instance. The emulator closes its sessions through
 * its lifecycle API; PHP continues normally. No server implementation is consulted.
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
     * @param TableDefinition|View|null $destination The destination identity at preparation
     */
    public function __construct(public readonly DeferredCreateTable $definition, public readonly string $database, public TableDefinition|View|null $destination)
    {
    }

    /**
     * Captures only the release and declaration family with this observed behavior.
     */
    public static function capture(DeferredCreateTable $definition, string $database, Session $session): ?self
    {
        if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5744 || !(new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($definition->elements)) {
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
        if ($session->instance->dictionary->schema($this->database) === null) {
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
        if ($current !== null) {
            foreach (['Com_stmt_reprepare', 'Com_stmt_prepare', 'Com_stmt_execute'] as $counter) {
                StatementCounters::command($session, $counter);
            }
            $this->destination = $current;
            $this->refreshed = true;
        } elseif ($this->refreshed && $this->disconnects() && $session->instance->dictionary->schema($this->database) !== null) {
            $session->instance->shutdown();
            throw TransactionError::ServerGone->error();
        }
    }

    /**
     * A bare system variable alone retains its file diagnostic after invalidation.
     * Other retained generation operands reproduce the independently observed disconnect.
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
            if (!$expression instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable) {
                return true;
            }
        }
        return false;
    }
}
