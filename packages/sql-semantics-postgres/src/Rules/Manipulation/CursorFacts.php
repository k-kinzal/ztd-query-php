<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\CursorOption;
use SqlSemantics\Platform\PostgreSql\Statement\Cursor\DeclareCursor;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;

/**
 * Derives the facts of DECLARE.
 *
 * Rule: PG-CURSOR-001. The query is derived as a nested query in the
 * empty environment; declaring the cursor returns no rows (FETCH reads
 * them). SCROLL with NO SCROLL, and ASENSITIVE with INSENSITIVE, are
 * reported. A data-modifying statement in the WITH clause of the query is
 * reported; one in a nested WITH clause follows PG-MODIFYING-CTE-001. A
 * selection with INTO is reported (PG-PLACEMENT-001).
 * Source: https://www.postgresql.org/docs/17/sql-declare.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CursorFacts
{
    /**
     * Derives a cursor declaration.
     */
    public function declare(DeclareCursor $declare, Derivation $derivation): void
    {
        $derivation->query($declare->query, $derivation->environment());
        $options = $declare->options;
        if (in_array(CursorOption::Scroll, $options, true) && in_array(CursorOption::NoScroll, $options, true)) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CursorScrollConflict));
        }
        if (in_array(CursorOption::Asensitive, $options, true) && in_array(CursorOption::Insensitive, $options, true)) {
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CursorSensitivityConflict));
        }
        $placement = new Placement();
        foreach ($placement->modifying($declare, $declare->query, $derivation)->tables ?? [] as $table) {
            if ($table->query instanceof Modification) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::CursorModifyingWith));
                break;
            }
        }
        $placement->values($declare, [], [], $derivation);
        $placement->into($declare, $derivation);
    }
}
