<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Flattens the recursive list productions of stored programs.
 *
 * Rule: MYSQL-ROUTINE-SEQUENCE-001. Scope: every list rule of the routine
 * family; the rule that reads a list names its productions. Each node along
 * the list spine must match one of them; the nonterminal members are
 * answered in source order and the separator tokens (commas, and the
 * semicolons that end the statements of a compound statement) are dropped,
 * since the writer adds them back. Terminates: an explicit stack visits
 * each spine node once. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Sequence
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Answers the members of a list in source order.
     *
     * @param list<string> $signatures The productions of the list rule
     * @return list<Node>
     * @throws ImplementationGap When a spine node matches none of the productions
     */
    public function items(Node $list, array $signatures): array
    {
        $items = [];
        $pending = [$list];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current->name !== $list->name) {
                $items[] = $current;
                continue;
            }
            $form = $this->lowering->form($current);
            if (!in_array($form->signature, $signatures, true)) {
                throw ImplementationGap::production($form);
            }
            for ($index = count($current->children) - 1; $index >= 0; $index--) {
                $child = $current->children[$index];
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return $items;
    }
}
