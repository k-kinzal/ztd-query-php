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
 * SHOW PROFILES: the statements the session profiled.
 *
 * Rule: MYSQL-SHOW-PROFILES-001. The columns are those of the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-profiles.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowProfiles implements Statement
{
    use Snapshot;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::Profiles);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'PROFILES');
    }
}
