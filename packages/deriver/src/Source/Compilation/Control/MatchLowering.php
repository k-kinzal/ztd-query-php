<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\Source\Compilation\Lowering;
use PhpParser\Node\Expr;

/**
 * Lowers match arms while keeping the default independent of source order.
 * @visibility root
 */
final class MatchLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Examines explicit arm conditions in order.
     * @param Expr\Match_ $node Match expression
     * @param string $subject Evaluated subject register
     * @param int $index Arm index
     * @return string Result register
     */
    public function arms(Expr\Match_ $node, string $subject, int $index): string
    {
        if (!isset($node->arms[$index])) {
            foreach ($node->arms as $arm) {
                if ($arm->conds === null) {
                    return $this->lowering->expression($arm->body);
                }
            }
            return $this->lowering->graph->emit($node, 'raise', name: 'UnhandledMatchError');
        }
        $arm = $node->arms[$index];
        if ($arm->conds === null) {
            return $this->arms($node, $subject, $index + 1);
        }
        return $this->conditions($node, $subject, $index, 0);
    }

    /**
     * Short-circuits the comma-separated alternatives of one arm.
     * @param Expr\Match_ $node Match expression
     * @param string $subject Subject register
     * @param int $armIndex Arm index
     * @param int $conditionIndex Condition index
     * @return string Selected value
     */
    public function conditions(Expr\Match_ $node, string $subject, int $armIndex, int $conditionIndex): string
    {
        $arm = $node->arms[$armIndex];
        $condition = $arm->conds[$conditionIndex] ?? null;
        if ($condition === null) {
            return $this->arms($node, $subject, $armIndex + 1);
        }
        $comparison = $this->lowering->graph->emit($node, 'binary', [$subject, $this->lowering->expression($condition)], '===');
        return (new ConditionalLowering($this->lowering))->choice($node, $comparison, fn (): string => $this->lowering->expression($arm->body), fn (): string => $this->conditions($node, $subject, $armIndex, $conditionIndex + 1));
    }
}
