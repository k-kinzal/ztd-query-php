<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Column;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * gram.y bounds FLOAT precision and ALTER COLUMN's positional form before parse analysis.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L2480-L2493
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L14460-L14484
 */
final class NumericContextRule implements RewriteRule
{
    /**
     * Restricts only a direct Iconst child, preserving signed statistics targets and nested expression constants.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name !== 'ICONST' || ($terminal->rules[count($terminal->rules) - 1] ?? null) !== 'Iconst') {
                continue;
            }
            $parent = $terminal->rules[count($terminal->rules) - 2] ?? '';
            $name = ['opt_float' => 'FLOAT_PRECISION_NUMBER', 'alter_table_cmd' => 'COLUMN_POSITION_NUMBER'][$parent] ?? null;
            if ($name !== null) {
                $source = 'gram.y:' . $parent . ':integer-domain';
                $sequence = $sequence->replace($index, 1, [$terminal->replaced($name, $source)], $source);
            }
        }
        return $sequence;
    }
}
