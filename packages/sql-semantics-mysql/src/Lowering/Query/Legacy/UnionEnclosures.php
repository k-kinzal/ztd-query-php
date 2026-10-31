<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Query\Legacy;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\UnenclosedUnion;

/**
 * Retains the completion boundary of MySQL 5.6's UNION enclosure condition.
 *
 * A first enclosed operand requires the later operands to be enclosed too.
 * Native 5.6 refuses the first complete unenclosed operand before resolving
 * names; 5.7 accepts the same combinations. Only an offset escapes lowering.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class UnionEnclosures
{
    /**
     * Reads complete statement-level UNION operands without retaining syntax.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($lowering->profile->grammar !== GrammarRelease::MySql5651 || !in_array($node->name, ['select', 'view_select_aux'], true)) {
            return null;
        }
        $form = $lowering->form($node->name === 'select' ? $lowering->form($node)->node(0) : $node);
        if (!in_array($form->signature, ['select_init: ( select_paren ) union_opt', 'view_select_aux: ( create_view_select_paren ) union_opt'], true)) {
            return null;
        }
        $union = $form->node(3);
        while ($lowering->form($union)->signature === 'union_opt: union_list') {
            $list = $lowering->form($lowering->form($union)->node(0));
            $operand = $lowering->form($list->node(2));
            if ($operand->signature === 'select_init: SELECT_SYM select_init2') {
                $part = $lowering->form($operand->node(1))->node(0);
                return $this->at($part);
            }
            if ($operand->signature !== 'select_init: ( select_paren ) union_opt') {
                return null;
            }
            $union = $operand->node(3);
        }
        return null;
    }

    /**
     * Publishes a completed operand's input boundary for diagnostic formatting.
     */
    public function at(Node $operand): RejectedInput
    {
        $end = (new SourceRanges())->span($operand)[1] ?? 0;
        return new RejectedInput(new UnenclosedUnion($end), $end, true);
    }
    /**
     * Finds an input refusal in complete query boundaries, including derived tables.
     */
    public function tree(Node $tree, Lowering $lowering): ?RejectedInput
    {
        $pending = [$tree];
        while ($pending !== []) {
            $node = array_pop($pending);
            $failure = $this->rejected($node, $lowering) ?? $this->derived($node, $lowering);
            if ($failure !== null) {
                return $failure;
            }
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }
        return null;
    }

    /**
     * Reads the operands of a derived-table UNION at its complete grammar boundary.
     */
    public function derived(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($lowering->profile->grammar !== GrammarRelease::MySql5651 || $node->name !== 'table_factor') {
            return null;
        }
        $form = $lowering->form($node);
        if ($form->signature !== 'table_factor: ( get_select_lex select_derived_union ) opt_table_alias') {
            return null;
        }
        $step = $lowering->form($form->node(2));
        $steps = [];
        while ($step->signature === 'select_derived_union: select_derived_union UNION_SYM union_option query_specification opt_union_order_or_limit') {
            $steps[] = [$step->node(3), $step->node(4)->tokens() === [] ? $step->node(3) : $step->node(4)];
            $step = $lowering->form($step->node(0));
        }
        $tokens = $step->node->tokens();
        if (($tokens[0]->name ?? '') !== '(') {
            return null;
        }
        $index = 0;
        while (($tokens[$index]->name ?? '') === '(') {
            $index++;
        }
        if (($tokens[$index]->name ?? '') !== 'SELECT_SYM') {
            return null;
        }
        foreach (array_reverse($steps) as [$operand, $end]) {
            if (($operand->tokens()[0]->name ?? '') === 'SELECT_SYM') {
                return $this->at($end);
            }
        }
        return null;
    }
}
