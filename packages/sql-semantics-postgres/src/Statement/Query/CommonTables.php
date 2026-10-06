<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * A WITH clause: the common table expressions a query or a data-modifying statement can refer to.
 *
 * Mirrors PostgreSQL's `WithClause` node. The statement that holds the
 * clause derives it once and evaluates its own parts in the environment the
 * clause answers, where the common tables are visible.
 * Source: https://www.postgresql.org/docs/17/queries-with.html.
 *
 * @visibility public
 * @example Naming the contract a WITH clause fulfils
 *     interface_exists(\SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables::class) // => true
 */
interface CommonTables extends Node
{
    /**
     * Derives the common table expressions and answers the environment in which they are visible.
     */
    public function deriveCommonTables(Derivation $derivation, Environment $outer): Environment;
}
