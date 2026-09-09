<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * parse_expr.c and parse_target.c allow up to four ColumnRef fields before subscripting.
 */
final class ColumnNameRule implements RewriteRule
{
    /**
     * Keeps the final column or star and leaves fields following the first subscript unrestricted.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $components = [];
        $subscripted = [];
        foreach (array_reverse($sequence->occurrences('indirection_el')) as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $parent = count($origin->rules) - 2;
            while (in_array($origin->rules[$parent] ?? null, ['indirection', 'opt_indirection'], true)) {
                --$parent;
            }
            if (($origin->rules[$parent] ?? null) !== 'columnref') {
                continue;
            }
            $scope = $origin->ancestors[$parent];
            if ($origin->name === '[') {
                $subscripted[$scope] = true;
            } elseif (!isset($subscripted[$scope])) {
                $components[$scope][] = $id;
            }
        }
        foreach ($components as $ids) {
            foreach (array_reverse(array_slice($ids, 0, max(0, count($ids) - 3))) as $id) {
                $range = $sequence->range($id);
                if ($range !== null) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'postgresql.column-name');
                }
            }
        }
        return $sequence;
    }
}
