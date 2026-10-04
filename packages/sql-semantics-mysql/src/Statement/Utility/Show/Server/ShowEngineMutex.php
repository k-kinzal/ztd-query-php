<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW ENGINE … MUTEX: the mutex statistics of a storage engine.
 *
 * Rule: MYSQL-SHOW-ENGINEMUTEX-001. The engine is a name the server resolves, or ALL for every
 * engine. The three SHOW ENGINE reports are distinct commands with the
 * same columns: those of the layout of MYSQL-SHOW-ROWS-001. Terminates: a
 * fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW ENGINE InnoDB MUTEX');
 *     [$show->statement->engine?->value, $show->toString()] // => ['InnoDB', 'SHOW ENGINE InnoDB MUTEX']
 */
final class ShowEngineMutex implements Statement
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
        $out->keyword('MUTEX');
    }
}
