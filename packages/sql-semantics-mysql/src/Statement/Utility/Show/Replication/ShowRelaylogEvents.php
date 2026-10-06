<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW RELAYLOG EVENTS: the events of a relay log file of a replica.
 *
 * Rule: MYSQL-SHOW-RELAYLOG-EVENTS-001. IN names the file, FROM the position of the first event, FOR
 * CHANNEL (5.7 and later) the replication channel. LIMIT selects rows as
 * in SELECT; its operands are derived at a position that sees no relation.
 * The columns are those of SHOW BINLOG EVENTS in the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-relaylog-events.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW RELAYLOG EVENTS LIMIT 1 FOR CHANNEL 'c'");
 *     [$show->statement->channel?->value, $show->toString()] // => ['c', "SHOW RELAYLOG EVENTS LIMIT 1 FOR CHANNEL 'c'"]
 */
final class ShowRelaylogEvents implements Statement
{
    use Snapshot;

    /**
     * @param ?Text $file The log file after IN; null for the first one
     * @param ?Numeral $position The position after FROM; null for the start of the file
     * @param ?Limit $limit The LIMIT clause
     * @param ?Text $channel The channel after FOR CHANNEL
     */
    public function __construct(public readonly ?Text $file = null, public readonly ?Numeral $position = null, public readonly ?Limit $limit = null, public readonly ?Text $channel = null)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = new ShowFacts();
        $facts->limit($derivation, $this->limit);
        $facts->rows($derivation, Report::LogEvents);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'RELAYLOG', 'EVENTS');
        if ($this->file !== null) {
            $out->keyword('IN')->node($this->file);
        }
        if ($this->position !== null) {
            $out->keyword('FROM')->node($this->position);
        }
        $out->node($this->limit);
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
