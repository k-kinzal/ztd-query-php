<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Declares the word domain checked by select.c/sqlite3JoinType and the source-list condition on ON/USING.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/select.c
 */
final class JoinRule implements RewriteRule
{
    /**
     * Identifies modifier positions structurally and removes conditions on a first, unjoined table.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('joinop') as $id) {
            foreach ($sequence->productions as $child) {
                if ($child->parent !== $id || $child->rule !== 'nm') {
                    continue;
                }
                $range = $sequence->range($child->id);
                if ($range !== null) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                        $sequence->terminals[$range[0]]->replaced('JOIN_MODIFIER', 'sqlite.join-modifier'),
                    ], 'sqlite.join-modifier');
                }
            }
        }
        foreach ($sequence->occurrences('seltablist') as $id) {
            $prefix = $sequence->child($id, 'stl_prefix');
            $condition = $sequence->child($id, 'on_using');
            if ($prefix === null || $condition === null || $sequence->range($prefix->id) !== null) {
                continue;
            }
            $range = $sequence->range($condition->id);
            if ($range !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'sqlite.unjoined-condition');
            }
        }
        return $sequence;
    }
}
