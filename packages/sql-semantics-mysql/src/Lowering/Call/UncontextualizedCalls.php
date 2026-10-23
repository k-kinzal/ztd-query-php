<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;

/**
 * Retains signatures until their enclosing name or CTE reference contextualizes.
 *
 * The input pass still checks syntax throughout these subtrees. Only native-call
 * signature checking is deferred to the owning semantic role. No parser node
 * escapes the input adapter.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UncontextualizedCalls
{
    /**
     * @return array<int, true> Occurrences whose call signatures belong to an outer role
     */
    public function inactive(Node $tree, Lowering $lowering): array
    {
        $inactive = [];
        /** @var list<array{Node, bool}> $pending */
        $pending = [[$tree, false]];
        while ($pending !== []) {
            [$node, $retained] = array_pop($pending);
            $retained = $retained || $node->name === 'common_table_expr';
            if (!$retained && $node->name === 'function_call_generic') {
                $form = $lowering->form($node);
                $position = $form->signature === 'function_call_generic: ident . ident ( opt_expr_list )' ? 2 : 0;
                $retained = ProgramNames::problem($lowering->names->identifier($form->node($position))) !== null;
            }
            if ($retained) {
                $inactive[spl_object_id($node)] = true;
            }
            foreach ($node->children as $child) {
                if ($child instanceof Node) {
                    $pending[] = [$child, $retained];
                }
            }
        }
        return $inactive;
    }
}
