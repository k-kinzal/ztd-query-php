<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy/opt_source_count requires the identifier COUNT and a nonzero NUM value.
 */
final class LoadSourceCountRule implements RewriteRule
{
    /**
     * Constrains the checked source-count production without changing LOAD identifiers or numbers elsewhere.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('opt_source_count') as $id) {
            $name = $sequence->child($id, 'IDENT_sys');
            $nameRange = $name === null ? null : $sequence->range($name->id);
            if ($nameRange !== null) {
                $sequence = $sequence->replace($nameRange[0], $nameRange[1] - $nameRange[0], [
                    $sequence->terminals[$nameRange[0]]->replaced('LOAD_COUNT_NAME', 'mysql.load-source-count'),
                ], 'mysql.load-source-count');
            }
            foreach ($sequence->terminals as $index => $terminal) {
                if ($terminal->name === 'NUM' && $terminal->ancestor('opt_source_count') === $id) {
                    $sequence = $sequence->replace($index, 1, [$terminal->replaced('LOAD_SOURCE_COUNT', 'mysql.load-source-count')], 'mysql.load-source-count');
                }
            }
        }
        return $sequence;
    }
}
