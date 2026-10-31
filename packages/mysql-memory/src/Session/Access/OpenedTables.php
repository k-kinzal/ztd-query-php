<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Records table handles opened during preparation and counts uses retained by sessions.
 *
 * A bound table is opened even when a later column error prevents execution. Unused CTE
 * bodies are omitted by the preparation walk; temporary tables never enter this cache.
 * Reading a user table in MySQL 8.0 and later also opens mysql.column_statistics,
 * including for an empty table or a false predicate; verified with FLUSH TABLES,
 * SELECT and SHOW OPEN TABLES on live 8.0, 8.4 and 9.1 servers.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/table-cache.html.
 *
 * @visibility MySqlMemory
 */
final class OpenedTables
{
    /**
     * Retains the base tables reached by the statement's preparation.
     *
     * @param list<Node> $nodes The nodes the server prepares
     */
    public function prepare(array $nodes, StatementSemantics $facts, Session $session): void
    {
        $writing = array_filter($nodes, static fn (Node $node): bool => $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete || $node instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Update || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete) !== [];
        foreach ($nodes as $node) {
            if (!($node instanceof TableReference || $node instanceof ExplicitTable || $node instanceof WriteTarget) || !$node->hasBinding()) {
                continue;
            }
            $resolution = $node->relationInformation()->table;
            if ($resolution instanceof DeclaredTable) {
                $name = $resolution->table->name;
                $this->open($name->schema->value ?? $session->variables->database, $name->name->value, $session, $node instanceof WriteTarget || $writing);
            }
        }
    }

    /**
     * Visits FROM sources before scalar subqueries and expands each resolved CTE use.
     * Unused definitions are skipped; recursive self references do not reopen the base input.
     *
     * @param array<int, true> $active The common tables already expanding on this branch
     *
     * @return list<TableReference|ExplicitTable>
     */
    public function references(Node $statement, array $active = []): array
    {
        if ($statement instanceof TableReference || $statement instanceof ExplicitTable) {
            $resolution = $statement->hasBinding() ? $statement->relationInformation()->table : null;
            if ($resolution instanceof \SqlSemantics\Statement\Reference\Table\CommonTable && $resolution->definition instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression) {
                $id = spl_object_id($resolution->definition);
                return isset($active[$id]) ? [] : $this->references($resolution->definition->query, $active + [$id => true]);
            }
            return [$statement];
        }
        $properties = get_object_vars($statement);
        unset($properties['with']);
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select) {
            $properties = ['from' => $statement->from] + $properties;
        } elseif ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
            $properties = ['body' => $statement->body] + $properties;
        } elseif ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation) {
            $properties = ['left' => $statement->left, 'right' => $statement->right] + $properties;
        }
        $references = [];
        $pending = array_reverse(array_values($properties));
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_array($node)) {
                array_push($pending, ...array_reverse(array_values($node)));
            } elseif ($node instanceof Node) {
                array_push($references, ...$this->references($node, $active));
            }
        }
        return $references;
    }

    /**
     * Opens a stored base table, leaving views and temporary tables out of the cache.
     */
    public function open(string $schema, string $name, Session $session, bool $write = false): void
    {
        $dictionary = $session->instance->dictionary;
        $view = $dictionary->schema($schema)->views[$name] ?? null;
        $syntax = $view === null ? null : (new StoredViewSyntax())->error($view);
        if ($syntax !== null) {
            throw $syntax;
        }
        $table = $dictionary->table($schema, $name, $session->temporaries);
        if ($table !== null && in_array($session->transaction->xa, [\MySqlMemory\Session\XaState::Idle, \MySqlMemory\Session\XaState::Prepared], true)) {
            throw \MySqlMemory\Error\Family\StatementError::XaWrongState->error($session->transaction->xa->value);
        }
        if ($table !== null && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 && \MySqlMemory\Session\Transaction::transactional($table) && $session->transaction->xa === \MySqlMemory\Session\XaState::NonExisting) {
            $session->transaction->legacyXid = null;
        }
        if ($table !== null && !$table->definition->temporary) {
            (new \MySqlMemory\Storage\Tablespaces())->opened($table, $session);
            ($session->preparation->triggerNotices ?? new \MySqlMemory\Hint\TriggerNotices())->load($schema, $name, $session, $write);
            $dictionary->cache->open($schema, $name);
            if (!$session->settings()->legacy() && !in_array($schema, ['mysql', 'information_schema', 'performance_schema', 'sys'], true)) {
                $dictionary->cache->open('mysql', 'column_statistics');
            }
        }
    }

    /**
     * Opens the dictionary view and dependencies read by SHOW TABLES in MySQL 8.0 and later.
     * A missing database opens only schemata; a false listing predicate still opens the view.
     * Verified through FLUSH TABLES and SHOW OPEN TABLES on MySQL 8.0, 8.4 and 9.1.
     */
    public function listing(string $database, Session $session): void
    {
        if ($session->settings()->legacy() || $database === '') {
            return;
        }
        $dictionary = $session->instance->dictionary;
        $dictionary->cache->open('mysql', 'schemata');
        if ($dictionary->schema($database) === null) {
            return;
        }
        $dictionary->cache->open('information_schema', 'TABLES');
        foreach (['catalogs', 'collations', 'tables', 'table_stats', 'tablespaces'] as $name) {
            $dictionary->cache->open('mysql', $name);
        }
    }

    /**
     * Opens routine metadata for a cold lookup, retaining definitions across table flushes.
     * MySQL 5.x has session caches invalidated by any routine DDL. Newer releases share loaded
     * dictionary definitions and invalidate only the altered routine. Missing routines are not
     * cached. Observed through repeated CALL, SELECT, FLUSH TABLES and SHOW OPEN TABLES.
     */
    public function routine(?Routine $routine, Session $session): void
    {
        $dictionary = $session->instance->dictionary;
        if (!$session->settings()->legacy()) {
            if ($routine !== null) {
                (new \MySqlMemory\Program\Routine\LoadNotices())->record($routine, $session);
            }
            if ($routine?->metadataLoaded === true) {
                return;
            }
            foreach (['routines', 'parameters', 'parameter_type_elements'] as $name) {
                $dictionary->cache->open('mysql', $name);
            }
            if ($routine !== null) {
                $routine->metadataLoaded = true;
            }

            return;
        }
        $prepared = $session->preparation;
        if ($prepared->routineGeneration !== $dictionary->routineGeneration) {
            $prepared->routineGeneration = $dictionary->routineGeneration;
            $prepared->routines = [];
        }
        if ($routine !== null && isset($prepared->routines[spl_object_id($routine)])) {
            return;
        }
        $dictionary->cache->open('mysql', 'proc');
        if ($routine !== null) {
            (new \MySqlMemory\Program\Routine\LoadNotices())->record($routine, $session);
            $prepared->routines[spl_object_id($routine)] = true;
        }
    }

    /**
     * Counts LOCK TABLES aliases and HANDLER cursors retained by every live session.
     */
    public function uses(string $schema, string $table, Session $session): int
    {
        $uses = 0;
        foreach ($session->instance->sessions as $reference) {
            $other = $reference->get();
            if ($other === null) {
                continue;
            }
            foreach ($other->locks as $lock) {
                $uses += !$lock->temporary && $lock->schema === $schema && $lock->table === $table ? 1 : 0;
            }
            foreach ($other->handlers as $handler) {
                $uses += $handler->schema === $schema && $handler->table === $table ? 1 : 0;
            }
        }

        return $uses;
    }
}
