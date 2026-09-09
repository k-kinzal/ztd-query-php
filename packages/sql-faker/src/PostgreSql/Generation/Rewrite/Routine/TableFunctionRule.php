<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y:mergeTableFuncParameters permits only default, IN or VARIADIC input modes.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class TableFunctionRule implements RewriteRule
{
    /**
     * Uses IN for OUT/INOUT arguments of RETURNS TABLE functions, preserving scalar functions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('arg_class') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $first = $sequence->terminals[$range[0]];
            $function = $first->ancestor('CreateFunctionStmt');
            $parameters = $first->ancestor('func_args_with_defaults');
            if ($function === null || $parameters === null || $sequence->child($function, 'table_func_column_list') === null
                || $sequence->child($function, 'func_args_with_defaults')?->id !== $parameters) {
                continue;
            }
            $names = array_slice($sequence->names(), $range[0], $range[1] - $range[0]);
            if (!in_array('OUT_P', $names, true) && !in_array('INOUT', $names, true)) {
                continue;
            }
            $source = 'gram.y:mergeTableFuncParameters';
            $sequence = $sequence->replace($range[0], $range[1] - $range[0], [$first->replaced('IN_P', $source)], $source);
        }
        return $sequence;
    }
}
