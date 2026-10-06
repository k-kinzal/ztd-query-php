<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement;

use SqlSemantics\Statement\Identifier\Name;

/**
 * An expression that gives a result column its name when the column has no alias.
 *
 * PostgreSQL names an unaliased result column after the expression: a column
 * reference after its column, a function call after the function, a cast after
 * its type, and so on; every other expression is named `?column?`.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-SELECT-LIST.
 *
 * @visibility public
 * @example Reading the name a typed constant gives its column
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT integer '1'");
 *     $query->statement->targets[0]->expression->outputName()?->value // => 'int4'
 */
interface OutputNaming
{
    /**
     * Answers the name the expression gives an unaliased result column, or null when it gives none.
     */
    public function outputName(): ?Name;
}
