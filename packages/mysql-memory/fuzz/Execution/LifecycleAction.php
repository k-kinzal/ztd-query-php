<?php

declare(strict_types=1);

namespace Fuzz\Execution;

use SqlParser\Parser\Node;

/**
 * Classifies top-level server lifecycle commands through the official grammar.
 * Comments, string contents and statements nested in stored definitions do not
 * select the lifecycle observer.
 */
enum LifecycleAction
{
    case Shutdown;
    case Restart;

    /**
     * Follows only the grammar's statement enclosures, never arbitrary descendants.
     */
    public static function read(Node $statement): ?self
    {
        if ($statement->name === 'shutdown_stmt') {
            return self::Shutdown;
        }
        if ($statement->name === 'restart_server_stmt') {
            return self::Restart;
        }
        if (in_array($statement->name, ['query', 'verb_clause', 'statement', 'start_entry', 'sql_statement', 'simple_statement_or_begin', 'simple_statement'], true)) {
            foreach ($statement->children as $child) {
                if ($child instanceof Node) {
                    return self::read($child);
                }
            }
        }

        return null;
    }
}
