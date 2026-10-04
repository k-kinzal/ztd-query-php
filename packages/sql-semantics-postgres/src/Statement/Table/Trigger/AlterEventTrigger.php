<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to enable or disable an event trigger.
 *
 * Mirrors PostgreSQL's `AlterEventTrigStmt` (`trigname`, `tgenabled`).
 * Source: https://www.postgresql.org/docs/17/sql-altereventtrigger.html.
 *
 * @visibility public
 * @example Disabling an event trigger
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EVENT TRIGGER e DISABLE');
 *     $statement->toString() // => 'ALTER EVENT TRIGGER e DISABLE'
 */
final class AlterEventTrigger implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The trigger name
     * @param FiringState $state The new state
     */
    public function __construct(public readonly Name $name, public readonly FiringState $state)
    {
    }

    /**
     * Derives nothing: the statement names a catalog object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'EVENT', 'TRIGGER')->name($this->name)->keyword(...$this->state->keywords());
    }
}
