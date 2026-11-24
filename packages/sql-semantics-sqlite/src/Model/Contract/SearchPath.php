<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Contract;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * The schemas a session searches for an unqualified relation name, in precedence order.
 *
 * @visibility public
 * @example Selecting SQLite main and an unnamed attachment
 *     (new \SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath('main', ''))->schemas // => ['main', '']
 */
final class SearchPath
{
    /**
     * @var non-empty-list<string> The schema names in precedence order
     */
    public readonly array $schemas;

    /**
     * @param string ...$schemas The schema names in precedence order; at least one
     */
    public function __construct(string ...$schemas)
    {
        $names = array_values($schemas);
        Check::input($names !== [], 'A search path names at least one schema.');
        $this->schemas = $names;
    }
}
