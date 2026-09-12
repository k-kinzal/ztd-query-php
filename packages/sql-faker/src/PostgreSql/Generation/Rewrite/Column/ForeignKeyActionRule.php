<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Column;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * key_update rejects SET NULL/DEFAULT column lists; key_delete permits them.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L4432-L4444
 */
final class ForeignKeyActionRule implements RewriteRule
{
    /**
     * Keeps the action and removes only its update-specific column list.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('key_update') as $owner) {
            $action = $sequence->child($owner, 'key_action');
            $columns = $action === null ? null : $sequence->child($action->id, 'opt_column_list');
            $range = $columns === null ? null : $sequence->range($columns->id);
            if ($range !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:key_update:column-list');
            }
        }
        return $sequence;
    }
}
