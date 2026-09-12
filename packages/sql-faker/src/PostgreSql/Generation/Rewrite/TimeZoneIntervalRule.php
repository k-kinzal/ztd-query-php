<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Implements gram.y/zone_value's HOUR and MINUTE interval-mask restriction.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class TimeZoneIntervalRule implements RewriteRule
{
    /**
     * Leaves ordinary interval types and valid zone modifiers unchanged.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('zone_value') as $id) {
            $interval = $sequence->child($id, 'opt_interval');
            $range = $interval === null ? null : $sequence->range($interval->id);
            if ($range === null) {
                continue;
            }
            $names = array_slice($sequence->names(), $range[0], $range[1] - $range[0]);
            if (array_intersect($names, ['YEAR_P', 'MONTH_P', 'DAY_P', 'SECOND_P']) !== []) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                    $sequence->insertedFor('HOUR_P', $interval->id, 'gram.y:zone_value'),
                ], 'gram.y:zone_value');
            }
        }
        return $sequence;
    }
}
