<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;

/**
 * A part of a statement that is not itself an expression, relation or query but may hold some.
 *
 * Type names, option lists, sort items, window specifications and object
 * signatures are clauses. Whoever holds a clause derives it exactly once, in
 * the environment its expressions are evaluated in, so every expression inside
 * it receives its facts.
 *
 * @visibility SqlSemantics
 */
interface Clause extends Node
{
    /**
     * Derives the expressions, relations and queries the clause holds.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void;
}
