<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

/**
 * Selects SQLite for semantic analysis.
 *
 * @visibility public
 * @example Identifying the selected database
 *     \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->database() // => 'sqlite'
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
