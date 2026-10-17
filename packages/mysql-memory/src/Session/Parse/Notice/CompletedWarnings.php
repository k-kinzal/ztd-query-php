<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Notice;

use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod;

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
     * @return array<int, HeartbeatPeriod|Deprecated> Notices indexed by completion offset
     */
    public function before(string $statement, int $end, \MySqlMemory\Session\Session $session): array
    {
        $parser = $session->semantics()->parser();
        \SqlSemantics\Diagnostic\Check::invariant($parser instanceof \SqlParser\Parser\ReductionParser, 'The MySQL parser reports completed productions.');
        $warnings = [];
        foreach ((new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout) as [$offset, $notice]) {
            if ($notice instanceof HeartbeatPeriod || $notice === Deprecated::FlushQueryCache) {
                $warnings[$offset] = $notice;
            }
        }

        return $warnings;
    }
}
