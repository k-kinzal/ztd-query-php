<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;

/**
 * SHOW COUNT(*) ERRORS: the number of errors of the last statement of the session.
 *
 * Rule: MYSQL-SHOW-ERRORCOUNT-001. The server reads it as `SELECT @@session.error_count`, which
 * names its one column. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-errors.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowErrorCount implements Statement
{
    use Snapshot;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::ErrorCount);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'COUNT')->glue()->symbol('(')->symbol('*')->symbol(')')->keyword('ERRORS');
    }
}
