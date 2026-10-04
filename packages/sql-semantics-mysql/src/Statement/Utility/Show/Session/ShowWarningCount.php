<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW COUNT(*) WARNINGS: the number of warnings of the last statement of the session.
 *
 * Rule: MYSQL-SHOW-WARNINGCOUNT-001. The server reads it as `SELECT @@session.warning_count`, which
 * names its one column. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW COUNT(*) WARNINGS');
 *     [$show->field(0)->name?->value, $show->toString()] // => ['@@session.warning_count', 'SHOW COUNT(*) WARNINGS']
 */
final class ShowWarningCount implements Statement
{
    use Snapshot;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::WarningCount);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'COUNT')->glue()->symbol('(')->symbol('*')->symbol(')')->keyword('WARNINGS');
    }
}
