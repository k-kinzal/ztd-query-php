<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy commit/rollback reject simultaneous affirmative CHAIN and RELEASE.
 */
final class TransactionCompletionRule implements RewriteRule
{
    /**
     * Keeps the selected chain behavior and makes the conflicting release explicitly negative.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (['commit', 'rollback'] as $rule) {
            foreach ($sequence->occurrences($rule) as $id) {
                $chain = $sequence->child($id, 'opt_chain');
                $release = $sequence->child($id, 'opt_release');
                $chainRange = $chain === null ? null : $sequence->range($chain->id);
                $releaseRange = $release === null ? null : $sequence->range($release->id);
                if ($chainRange === null || $releaseRange === null) {
                    continue;
                }
                $names = array_slice($sequence->names(), $chainRange[0], $chainRange[1] - $chainRange[0]);
                if ($names === ['AND_SYM', 'CHAIN_SYM'] && $sequence->nameAt($releaseRange[0]) === 'RELEASE_SYM') {
                    $source = 'sql_yacc.yy:commit/rollback:chain-release';
                    $sequence = $sequence->replace($releaseRange[0], 0, [
                        $sequence->inserted('NO_SYM', $sequence->terminals[$releaseRange[0]], $source),
                    ], $source);
                }
            }
        }
        return $sequence;
    }
}
