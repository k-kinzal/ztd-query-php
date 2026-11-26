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
 * SHOW COUNT(*) WARNINGS: the number of warnings of the last statement of the session.
 *
 * Rule: MYSQL-SHOW-WARNINGCOUNT-001. The server reads it as `SELECT @@session.warning_count`, which
 * names its one column. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-warnings.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
