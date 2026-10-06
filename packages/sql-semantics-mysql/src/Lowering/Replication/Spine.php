<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;

/**
 * Flattens a left-recursive list of the replication family after confirming every production of its spine.
 *
 * Rule: MYSQL-REPLICATION-LIST-001. Every node of the list rule along the
 * spine must match one of the given productions; the items are answered in
 * written order, separators and parentheses dropped. Terminates: the spine
 * is walked iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-replication-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
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
     * Answers the items of a list whose spine nodes match the given productions.
     *
     * @param list<string> $signatures
     * @return list<Node>
     * @throws ImplementationGap When a spine node matches another production
     */
    public function items(Node $list, array $signatures): array
    {
        $current = $list;
        while (true) {
            $form = $this->lowering->form($current);
            if (!in_array($form->signature, $signatures, true)) {
                throw ImplementationGap::production($form);
            }
            $first = $current->children[0] ?? null;
            if (!$first instanceof Node || $first->name !== $list->name) {
                break;
            }
            $current = $first;
        }

        return (new Lists())->items($list);
    }
}
