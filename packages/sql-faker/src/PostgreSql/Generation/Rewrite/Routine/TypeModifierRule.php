<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * gram.y:AexprConst reuses function arguments for type modifiers but rejects names and ordering.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class TypeModifierRule implements RewriteRule
{
    /**
     * Retains type-modifier expressions and leaves function calls inside those expressions intact.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('AexprConst') as $id) {
            $arguments = $sequence->child($id, 'func_arg_list');
            if ($arguments === null) {
                continue;
            }
            $sequence = $this->arguments($sequence, $arguments->id);
            $sort = $sequence->child($id, 'opt_sort_clause');
            $range = $sort === null ? null : $sequence->range($sort->id);
            if ($range !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:AexprConst:type-modifier');
            }
        }
        return $sequence;
    }

    /**
     * Visits only the selected list's direct arguments, stopping before their nested expressions.
     */
    public function arguments(TerminalSequence $sequence, int $list): TerminalSequence
    {
        $pending = [$list];
        while ($pending !== []) {
            $parent = array_pop($pending);
            foreach ($sequence->productions as $child) {
                if ($child->parent !== $parent) {
                    continue;
                }
                if ($child->rule === 'func_arg_list') {
                    $pending[] = $child->id;
                } elseif ($child->rule === 'func_arg_expr') {
                    $value = $sequence->child($child->id, 'a_expr');
                    $range = $sequence->range($child->id);
                    $valueRange = $value === null ? null : $sequence->range($value->id);
                    if ($range !== null && $valueRange !== null && $range[0] < $valueRange[0]) {
                        $sequence = $sequence->replace($range[0], $valueRange[0] - $range[0], [], 'gram.y:AexprConst:type-modifier');
                    }
                }
            }
        }
        return $sequence;
    }
}
