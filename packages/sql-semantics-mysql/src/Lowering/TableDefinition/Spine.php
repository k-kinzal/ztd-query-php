<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Flattens the recursive list rules of the table definition family and checks every production of the spine.
 *
 * Rule: MYSQL-TABLE-DEFINITION-LIST-001. A list node and every nested node
 * of the same rule must match one of the list productions the caller names;
 * the nodes of the rules the caller asks for are returned in source order,
 * separators and noise rules left out. Terminates: an explicit stack over
 * strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Spine
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Answers the item nodes of a list in source order.
     *
     * @param list<string> $signatures The productions of the list rule
     * @param list<string> $items The rule names of the items to return
     * @return list<Node>
     * @throws ImplementationGap When a spine node matches another production
     */
    public function items(Node $list, array $signatures, array $items): array
    {
        $found = [];
        $pending = [$list];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (!$current instanceof Node) {
                continue;
            }
            if ($current->name === $list->name) {
                $form = $this->lowering->productions->form($current);
                if (!in_array($form->signature, $signatures, true)) {
                    throw ImplementationGap::production($form);
                }
                for ($index = count($current->children) - 1; $index >= 0; $index--) {
                    $pending[] = $current->children[$index];
                }
            } elseif (in_array($current->name, $items, true)) {
                $found[] = $current;
            }
        }

        return $found;
    }
}
