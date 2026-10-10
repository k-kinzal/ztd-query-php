<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Fact\Facts;
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
    public function prepare(array $nodes, Facts $facts, Session $session): void
    {
        foreach ($nodes as $node) {
            if (!($node instanceof TableReference || $node instanceof ExplicitTable || $node instanceof WriteTarget) || !$facts->covers($node)) {
                continue;
            }
            $resolution = $facts->relation($node)->table;
            if ($resolution instanceof DeclaredTable) {
                $name = $resolution->table->name;
                $this->open($name->schema->value ?? $session->variables->database, $name->name->value, $session);
            }
        }
    }

    /**
     * Opens a stored base table, leaving views and temporary tables out of the cache.
     */
    public function open(string $schema, string $name, Session $session): void
    {
        $dictionary = $session->instance->dictionary;
        $table = $dictionary->table($schema, $name);
        if ($table !== null && !$table->definition->temporary) {
            $dictionary->cache->open($schema, $name);
            if (!$session->settings()->legacy() && !in_array($schema, ['mysql', 'information_schema', 'performance_schema', 'sys'], true)) {
                $dictionary->cache->open('mysql', 'column_statistics');
            }
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
            foreach ($other->locks as [$database, $name]) {
                $uses += $database === $schema && $name === $table ? 1 : 0;
            }
            foreach ($other->handlers as $handler) {
                $uses += $handler->schema === $schema && $handler->table === $table ? 1 : 0;
            }
        }

        return $uses;
    }
}
