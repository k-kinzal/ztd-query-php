<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\ComparisonWidth;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Statement\Scalar;

/**
 * Contextualizes comparison operands before reading later expressions.
 *
 * A complete statement is required. The refusal allows grammar notices from
 * that statement, while a stored instruction stops before the following one.
 * No parser node or original SQL escapes this input boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ComparisonRule
{
    /**
     * Lowers a comparison, retaining MySQL 5.6's deferred width check.
     *
     * @throws RejectedInput When written rows have incompatible outer widths
     */
    public function lower(ComparisonOperator $operator, Scalar|RowOperand $left, Scalar|RowOperand $right, Node $source, GrammarRelease $release): Comparison
    {
        $problem = (new ComparisonWidth())->problem($left, $right, $release);
        if ($problem !== null) {
            throw new RejectedInput($problem, (new SourceRanges())->span($source)[1] ?? 0, false);
        }
        (new OperandRule())->check((new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\RowShapes())->problem($left, $right), $source);
        return new Comparison($operator, $left, $right);
    }

    /**
     * Checks only a written-row width conflict in the operand refusal prepass.
     * Other expressions retain their ordinary contextualization boundary.
     */
    public function rejected(Node $node, \SqlSemantics\Platform\MySql\Lowering\Lowering $lowering): ?RejectedInput
    {
        if ($lowering->profile->grammar === GrammarRelease::MySql5651 || !in_array($lowering->form($node)->signature, ['bool_pri: bool_pri comp_op predicate', 'bool_pri: bool_pri EQUAL_SYM predicate'], true)) {
            return null;
        }
        $form = $lowering->form($node);
        $left = $this->writtenWidth($form->node(0), $lowering);
        $right = $this->writtenWidth($form->node(2), $lowering);
        if ($left === null || $right === null || $left === $right) {
            return null;
        }
        try {
            (new ConditionRule($lowering))->boolean($node);
        } catch (RejectedInput $failure) {
            return $failure;
        }
        return null;
    }

    /**
     * Recognizes row syntax without contextualizing unrelated scalar operands.
     */
    public function writtenWidth(Node $node, \SqlSemantics\Platform\MySql\Lowering\Lowering $lowering): ?int
    {
        while (true) {
            $form = $lowering->form($node);
            if (in_array($form->signature, ['expr: bool_pri', 'bool_pri: predicate', 'predicate: bit_expr', 'bit_expr: simple_expr'], true)) {
                $node = $form->node(0);
            } elseif ($form->signature === 'simple_expr: ( expr )') {
                $node = $form->node(1);
            } else {
                $list = match ($form->signature) {
                    'simple_expr: ( expr , expr_list )' => $form->node(3),
                    'simple_expr: ROW_SYM ( expr , expr_list )' => $form->node(4),
                    default => null,
                };
                return $list === null ? null : 1 + count((new \SqlSemantics\Lowering\Lists())->items($list));
            }
        }
    }

}
