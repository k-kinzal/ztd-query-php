<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW REPLICA STATUS (SHOW SLAVE STATUS): the state of the replication threads.
 *
 * Rule: MYSQL-SHOW-REPLICA-STATUS-001. The two spellings are kept: they name the columns differently
 * (`Slave_IO_State`, `Master_Host` against `Replica_IO_State`,
 * `Source_Host`), and each release accepts only some of them. FOR CHANNEL
 * (5.7 and later) selects a channel. The columns are those of the layout
 * of MYSQL-SHOW-ROWS-001 for the spelling and release. Terminates: a fixed
 * layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-replica-status.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW REPLICA STATUS FOR CHANNEL 'c'");
 *     [$show->field(0)->name?->value, $show->toString()] // => ['Replica_IO_State', "SHOW REPLICA STATUS FOR CHANNEL 'c'"]
 */
final class ShowReplicaStatus implements Statement
{
    use Snapshot;

    /**
     * @param Terminology $terminology Whether SLAVE or REPLICA is written
     * @param ?Text $channel The channel after FOR CHANNEL
     */
    public function __construct(public readonly Terminology $terminology, public readonly ?Text $channel = null)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, $this->terminology === Terminology::Legacy ? Report::SlaveStatus : Report::ReplicaStatus);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        match ($this->terminology) {
            Terminology::Legacy => $out->keyword('SHOW', 'SLAVE', 'STATUS'),
            Terminology::Current => $out->keyword('SHOW', 'REPLICA', 'STATUS'),
        };
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
