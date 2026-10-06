<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

/**
 * Selects MySQL for semantic analysis.
 *
 * @visibility public
 * @example Analyzing MySQL SQL
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $semantics->analyze('select a from t')->toString() // => 'SELECT a FROM t'
 */
enum Dialect: string implements \SqlSemantics\Contract\Dialect
{
    case MySql = 'mysql';

    /**
     * Names the database family as the grammar releases do.
     */
    public function database(): string
    {
        return $this->value;
    }
}
