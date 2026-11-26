<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;

/**
 * SHOW PROCESSLIST: the threads running in the server.
 *
 * Rule: MYSQL-SHOW-PROCESSLIST-001. FULL returns the whole statement text in `Info` instead of its
 * first 100 characters, which changes the type of that column. The columns
 * are those of the layout of MYSQL-SHOW-ROWS-001. Terminates: a fixed
 * layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-processlist.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowProcesslist implements Statement
{
    use Snapshot;

    /**
     * @param bool $full Whether FULL is written
     */
    public function __construct(public readonly bool $full = false)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, $this->full ? Report::FullProcesslist : Report::Processlist);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW');
        if ($this->full) {
            $out->keyword('FULL');
        }
        $out->keyword('PROCESSLIST');
    }
}
