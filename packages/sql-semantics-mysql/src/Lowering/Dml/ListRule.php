<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Flattens the list productions of the dml family.
 *
 * Rule: MYSQL-DML-LIST-001. A list nonterminal is left or right recursive;
 * every node of its spine must match one of the productions the caller names,
 * and the members are the nonterminal children that are not the spine, in
 * written order. Separators are punctuation of the list. Terminates: an
 * explicit work stack over the finite tree. Source: the statement pages of
 * the callers. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Dml
 */
final class ListRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Answers the members of a list in written order.
     *
     * @param list<string> $signatures The productions of the list nonterminal
     * @return list<Node>
     * @throws ImplementationGap When a node of the spine matches another production
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
