<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Triggers;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to create an event trigger: a function that runs on DDL events.
 *
 * Mirrors PostgreSQL's `CreateEventTrigStmt` (`trigname`, `eventname`, `whenclause`, `funcname`). The event
 * must be one the server knows and the filter variable must be `tag`; others are reported. PROCEDURE for
 * FUNCTION is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-createeventtrigger.html.
 *
 * @visibility SqlSemantics
 */
final class CreateEventTrigger implements Statement
{
    use Snapshot;

    /**
     * @var list<EventFilter> The WHEN items
     */
    public readonly array $filters;

    /**
     * @param Name $name The trigger name
     * @param Name $event The event
     * @param DottedName $function The function
     * @param list<EventFilter> $filters The WHEN items
     */
    public function __construct(
        public readonly Name $name,
        public readonly Name $event,
        public readonly DottedName $function,
        array $filters = [],
    ) {
        $this->filters = Check::listOf($filters, EventFilter::class, 'Event filters are filters.');
    }

    /**
     * Reports an unknown event or filter variable.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Triggers())->event($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'EVENT', 'TRIGGER')->name($this->name)->keyword('ON')->name($this->event, NameUse::Label);
        if ($this->filters !== []) {
            $out->keyword('WHEN');
            (new Writing())->separated($out, $this->filters, 'AND');
        }
        $out->keyword('EXECUTE', 'FUNCTION')->node($this->function)->glue()->symbol('(')->symbol(')');
    }
}
