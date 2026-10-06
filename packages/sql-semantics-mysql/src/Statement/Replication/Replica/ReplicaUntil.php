<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Replica;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The UNTIL clause of START REPLICA: an optional GTID or gap condition first, then log positions, in written order.
 *
 * The grammar admits a GTID or gap condition only as the first item, so the
 * two parts keep the written order exactly. Which combinations the server
 * accepts is checked when the statement is derived (MYSQL-REPLICA-UNTIL-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-replica.html.
 *
 * @visibility public
 * @example Holding a GTID condition
 *     $until = new \SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaUntil(\SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint::AfterGtids, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('3E11FA47-71CA-11E1-9E33-C80AA9429562:1-5'));
 *     $until->positions // => []
 */
final class ReplicaUntil implements Node
{
    use Snapshot;

    /**
     * @var list<SourceOption> The log positions in written order
     */
    public readonly array $positions;

    /**
     * @param UntilPoint|null $point The GTID or gap condition written first, if any
     * @param Text|null $gtids The GTID set of a GTID condition
     * @param list<SourceOption> $positions The log file and position options in written order
     */
    public function __construct(public readonly ?UntilPoint $point, public readonly ?Text $gtids = null, array $positions = [])
    {
        $this->positions = Check::listOf($positions, SourceOption::class, 'UNTIL lists log positions.');
        Check::input(($gtids !== null) === ($point === UntilPoint::BeforeGtids || $point === UntilPoint::AfterGtids), 'A GTID condition, and only it, holds a GTID set.');
        Check::input($point !== null || $this->positions !== [], 'UNTIL holds at least one condition.');
        foreach ($this->positions as $position) {
            Check::input($position->kind->position(), 'UNTIL lists only log file and position options.');
        }
    }

    /**
     * Writes UNTIL and the conditions.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNTIL');
        if ($this->point !== null) {
            $out->keyword($this->point->value);
            if ($this->gtids !== null) {
                $out->symbol('=')->node($this->gtids);
            }
            if ($this->positions !== []) {
                $out->symbol(',');
            }
        }
        $out->list($this->positions);
    }
}
