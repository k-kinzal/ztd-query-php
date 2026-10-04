<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW REPLICAS (SHOW SLAVE HOSTS): the replicas registered with the source.
 *
 * Rule: MYSQL-SHOW-REPLICAS-001. The two spellings are kept: they name the columns differently
 * (`Master_id`, `Slave_UUID` against `Source_Id`, `Replica_UUID`), and
 * each release accepts only some of them (SHOW SLAVE HOSTS up to 8.3, SHOW
 * REPLICAS from 8.0). The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001 for the spelling. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-replicas.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.0.44'))->analyze('SHOW SLAVE HOSTS');
 *     [$show->field(4)->name?->value, $show->toString()] // => ['Slave_UUID', 'SHOW SLAVE HOSTS']
 */
final class ShowReplicas implements Statement
{
    use Snapshot;

    /**
     * @param Terminology $terminology Whether SLAVE HOSTS or REPLICAS is written
     */
    public function __construct(public readonly Terminology $terminology)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, $this->terminology === Terminology::Legacy ? Report::ReplicaHosts : Report::Replicas);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        match ($this->terminology) {
            Terminology::Legacy => $out->keyword('SHOW', 'SLAVE', 'HOSTS'),
            Terminology::Current => $out->keyword('SHOW', 'REPLICAS'),
        };
    }
}
