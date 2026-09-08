<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Completes source-defined integer contexts in sql_yacc.yy, including diagnostic decimal alternatives.
 */
final class IntegerContextRule implements RewriteRule
{
    /**
     * Keeps ordinary numeric expressions unchanged and constrains only the checked grammar positions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('dec_num_error') as $id) {
            $sequence = $this->mapped($sequence, $id, 'NUM', 'mysql.integer-required');
        }
        foreach ($sequence->occurrences('source_def') as $id) {
            $range = $sequence->range($id);
            $number = $sequence->child($id, 'real_ulong_num');
            if ($range !== null && $number !== null && $sequence->nameAt($range[0]) === 'SOURCE_CONNECTION_AUTO_FAILOVER_SYM') {
                $sequence = $this->mapped($sequence, $number->id, 'REPLICATION_FLAG_NUMBER', 'mysql.replication-flag');
            }
        }
        foreach ($sequence->occurrences('xid') as $id) {
            $number = $sequence->child($id, 'ulong_num');
            $range = $number === null ? null : $sequence->range($number->id);
            if ($number !== null && $range !== null && $sequence->nameAt($range[0]) === 'ULONGLONG_NUM') {
                $sequence = $this->mapped($sequence, $number->id, 'NUM', 'mysql.xid-format-overflow');
            }
        }
        return $sequence;
    }

    /**
     * Retains occurrence identity so a compatible explicit Plan spelling survives contextual renaming.
     */
    public function mapped(TerminalSequence $sequence, int $production, string $terminal, string $source): TerminalSequence
    {
        $range = $sequence->range($production);
        return $range === null ? $sequence : $sequence->replace($range[0], $range[1] - $range[0], [
            $sequence->terminals[$range[0]]->replaced($terminal, $source),
        ], $source);
    }
}
