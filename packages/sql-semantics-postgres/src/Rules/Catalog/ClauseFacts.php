<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Resolution\Environment;

/**
 * Derives the clauses of a catalog command that sees no relation.
 *
 * Rule: PG-CATALOG-CLAUSE-001. Option values, type names and object
 * signatures of a catalog command may hold expressions, such as the
 * modifiers of a type name; they are evaluated where no relation is visible.
 * Every clause is derived exactly once. Termination: one pass over a finite
 * list. Source: https://www.postgresql.org/docs/17/sql-commands.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ClauseFacts
{
    /**
     * Derives each present clause in an environment without relations.
     *
     * @param list<Clause|null> $clauses
     */
    public function derive(Derivation $derivation, array $clauses): void
    {
        $environment = new Environment($derivation->context);
        foreach ($clauses as $clause) {
            $clause?->deriveClause($derivation, $environment);
        }
    }
}
