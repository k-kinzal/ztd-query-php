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
 * SHOW BINLOG EVENTS: the events of a binary log file.
 *
 * Rule: MYSQL-SHOW-BINLOG-EVENTS-001. IN names the file, FROM the position of the first event. LIMIT
 * selects rows as in SELECT; its operands are derived at a position that
 * sees no relation. The columns are those of the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-binlog-events.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SHOW BINLOG EVENTS IN 'binlog.000001' FROM 4 LIMIT 2");
 *     [$show->statement->file?->value, $show->toString()] // => ['binlog.000001', "SHOW BINLOG EVENTS IN 'binlog.000001' FROM 4 LIMIT 2"]
 */
final class ShowBinlogEvents implements Statement
{
    use Snapshot;

    /**
     * @param ?Text $file The log file after IN; null for the first one
     * @param ?Numeral $position The position after FROM; null for the start of the file
     * @param ?Limit $limit The LIMIT clause
     */
    public function __construct(public readonly ?Text $file = null, public readonly ?Numeral $position = null, public readonly ?Limit $limit = null)
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
        $out->keyword('SHOW', 'BINLOG', 'EVENTS');
        if ($this->file !== null) {
            $out->keyword('IN')->node($this->file);
        }
        if ($this->position !== null) {
            $out->keyword('FROM')->node($this->position);
        }
        $out->node($this->limit);
    }
}
