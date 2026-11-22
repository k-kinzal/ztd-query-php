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
 * SHOW PLUGINS: the plugins of the server.
 *
 * Rule: MYSQL-SHOW-PLUGINS-001. The columns are those of the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-plugins.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowPlugins implements Statement
{
    use Snapshot;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::Plugins);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'PLUGINS');
    }
}
