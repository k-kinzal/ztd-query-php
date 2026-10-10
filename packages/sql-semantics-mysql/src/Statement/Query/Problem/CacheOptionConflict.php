<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Two query cache modifiers a query block of MySQL 5.6 or 5.7 may not combine: one written twice (ER_DUP_ARGUMENT), or SQL_CACHE with SQL_NO_CACHE (ER_WRONG_USAGE).
 *
 * The server finds the problem while it parses the modifiers, so it stops the parse there.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/query-cache-in-select.html.
 *
 * @visibility public
 * @example Reading the problem of SQL_CACHE written twice in 5.7
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT SQL_CACHE SQL_CACHE 1');
 *     $query->facts->diagnostics[0]->message() // => "Option 'SQL_CACHE' used twice in statement"
 */
final class CacheOptionConflict implements Diagnostic
{
    use Snapshot;

    /**
     * @param SelectOption $first The modifier written first
     * @param SelectOption $second The modifier that conflicts with it
     */
    public function __construct(public readonly SelectOption $first, public readonly SelectOption $second)
    {
    }

    /**
     * Tells whether the same modifier is written twice, rather than two opposite ones.
     */
    public function repeated(): bool
    {
        return $this->first === $this->second;
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return $this->repeated() ? "Option '{$this->first->value}' used twice in statement" : "Incorrect usage of {$this->first->value} and {$this->second->value}";
    }
}
