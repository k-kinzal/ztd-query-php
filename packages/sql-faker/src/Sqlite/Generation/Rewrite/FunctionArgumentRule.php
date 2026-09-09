<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * SQLite 3.47.2 expr.c:sqlite3ExprFunction enforces sqliteLimit.h's default 127 arguments.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/expr.c
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/sqliteLimit.h
 */
final class FunctionArgumentRule implements RewriteRule
{
    /**
     * Limits only a function's direct arguments, preserving nested expressions and trailing options.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $children = [];
        $rules = [];
        foreach ($sequence->productions as $production) {
            $rules[$production->id] = $production->rule;
            if ($production->parent !== null) {
                $children[$production->parent][$production->rule] ??= $production->id;
            }
        }
        foreach (array_reverse($sequence->productions) as $list) {
            if ($list->rule !== 'exprlist' || $list->parent === null || ($rules[$list->parent] ?? '') !== 'expr' || !isset($children[$list->parent]['idj'])) {
                continue;
            }
            $arguments = [];
            $current = $children[$list->id]['nexprlist'] ?? null;
            while ($current !== null) {
                $argument = $children[$current]['expr'] ?? null;
                if ($argument !== null) {
                    array_unshift($arguments, $argument);
                }
                $current = $children[$current]['nexprlist'] ?? null;
            }
            if (count($arguments) <= 127) {
                continue;
            }
            $excess = $sequence->range($arguments[127]);
            $range = $sequence->range($list->id);
            if ($excess !== null && $range !== null && $sequence->nameAt($excess[0] - 1) === 'COMMA') {
                $sequence = $sequence->replace($excess[0] - 1, $range[1] - $excess[0] + 1, [], 'src/expr.c:sqlite3ExprFunction:SQLITE_MAX_FUNCTION_ARG');
            }
        }
        return $sequence;
    }
}
