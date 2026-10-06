<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\ModifyingCommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause;

/**
 * Derives a data-modifying statement that is a whole statement.
 *
 * Rule: PG-MODIFICATION-ROOT-001. The statement is derived in the empty
 * environment; the rows of its RETURNING list are its output, and without
 * RETURNING it returns no rows. A selection with INTO anywhere inside is
 * reported (PG-PLACEMENT-001), and so is a WITH clause holding a data-modifying
 * statement anywhere but before the statement itself (PG-MODIFYING-CTE-001).
 * Source: https://www.postgresql.org/docs/17/dml-returning.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Roots
{
    /**
     * Derives the statement and records its rows.
     */
    public function derive(Modification $root, Derivation $derivation): void
    {
        $fact = $derivation->query($root, $derivation->environment());
        (new Placement())->into($root, $derivation);
        $with = $root->commonTables();
        (new ModifyingCommonTables())->check($root, $with instanceof WithClause ? $with : null, $derivation);
        if ($root->returnsRows()) {
            $derivation->output($fact);
        }
    }
}
