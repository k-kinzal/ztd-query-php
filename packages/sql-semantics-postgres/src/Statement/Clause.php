<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * A part of a statement that is not itself an expression, relation or query but may hold some.
 *
 * Type names, option lists, sort items, window specifications and object
 * signatures are clauses. Whoever holds a clause derives it exactly once, in
 * the environment its expressions are evaluated in, so every expression inside
 * it receives its facts.
 *
 * @visibility public
 * @example Telling that a type name is a clause
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT numeric(4, 2) '1.5'");
 *     $query->statement->targets[0]->expression->type instanceof \SqlSemantics\Platform\PostgreSql\Statement\Clause // => true
 */
interface Clause extends Node
{
    /**
     * Derives the expressions, relations and queries the clause holds.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void;
}
