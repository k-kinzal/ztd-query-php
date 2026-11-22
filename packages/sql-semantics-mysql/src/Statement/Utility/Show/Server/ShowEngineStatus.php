<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;

/**
 * SHOW ENGINE … STATUS: the status report of a storage engine.
 *
 * Rule: MYSQL-SHOW-ENGINESTATUS-001. The engine is a name the server resolves, or ALL for every
 * engine. The three SHOW ENGINE reports are distinct commands with the
 * same columns: those of the layout of MYSQL-SHOW-ROWS-001. Terminates: a
 * fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowEngineStatus implements Statement
{
    use Snapshot;

    /**
     * @param ?Name $engine The storage engine, or null for ALL
     */
    public function __construct(public readonly ?Name $engine = null)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::Engine);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'ENGINE');
        if ($this->engine === null) {
            $out->keyword('ALL');
        } else {
            $out->name($this->engine, NameUse::Label);
        }
        $out->keyword('STATUS');
    }
}
