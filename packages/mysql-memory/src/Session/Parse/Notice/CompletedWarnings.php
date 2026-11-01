<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Notice;

use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;

/**
 * Collects completed semantic notices absent from legacy token-boundary scanners.
 *
 * @visibility MySqlMemory
 */
final class CompletedWarnings
{
    /**
     * Keeps each completed condition at its own written boundary.
     *
     * @param bool $parsed Whether the complete tree already supplies leading-dot notices
     * @return array<int, HeartbeatPeriod|NameConversion|Deprecated> Notices indexed by completion offset
     */
    public function before(string $statement, int $end, \MySqlMemory\Session\Session $session, bool $parsed = false): array
    {
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $warnings = [];
        foreach ((new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client) as [$offset, $notice]) {
            if ($notice instanceof HeartbeatPeriod || $notice instanceof NameConversion || in_array($notice, [Deprecated::GroupByDirection, Deprecated::FlushQueryCache, Deprecated::OldPassword, Deprecated::ExplainExtended, Deprecated::ExplainPartitions, Deprecated::AlterIgnore], true) || (!$parsed && in_array($notice, [Deprecated::DotTable, Deprecated::DotColumn], true))) {
                $warnings[$offset] = $notice;
            }
        }

        return $warnings;
    }
}
