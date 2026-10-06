<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

use SqlSemantics\Diagnostic\Check;

/**
 * The schemas a session searches for an unqualified relation name, in precedence order.
 *
 * @visibility public
 * @example Selecting the MySQL current database
 *     (new \SqlSemantics\Contract\SearchPath('shop'))->schemas // => ['shop']
 */
final class SearchPath
{
    /**
     * @var non-empty-list<string> The schema names in precedence order
     */
    public readonly array $schemas;

    /**
     * @param string ...$schemas The schema names in precedence order; at least one, none empty
     */
    public function __construct(string ...$schemas)
    {
        $names = array_values($schemas);
        Check::input($names !== [] && !in_array('', $names, true), 'A search path names at least one schema and no empty name.');
        $this->schemas = $names;
    }
}
