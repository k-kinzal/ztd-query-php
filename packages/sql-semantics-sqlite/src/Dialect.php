<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

/**
 * Selects SQLite for semantic analysis.
 *
 * @visibility public
 * @example Analyzing SQLite SQL
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $semantics->analyze('SELECT 42')->toString() // => 'SELECT 42'
 */
enum Dialect: string implements \SqlSemantics\Contract\Dialect
{
    case Sqlite = 'sqlite';

    /**
     * Names the database family as the grammar releases do.
     */
    public function database(): string
    {
        return $this->value;
    }
}
