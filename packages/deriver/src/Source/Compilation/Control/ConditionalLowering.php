<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\ControlFlow\Terminator;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\Lowering;
use Deriver\Value\Term;
use PhpParser\Node\Expr;

/**
 * Lowers conditional expressions into branches and Phi selections.
 * @visibility root
 */
final class ConditionalLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Creates lazily evaluated conditional alternatives.
     * @param Expr $node Conditional expression
     * @return string Result register
     */
    public function lower(Expr $node): string
    {
        if ($node instanceof Expr\Ternary) {
            $condition = $this->lowering->expression($node->cond);
            return $this->choice($node, $condition, fn (): string => $node->if === null ? $condition : $this->lowering->expression($node->if), fn (): string => $this->lowering->expression($node->else));
        }
        if ($node instanceof Expr\Match_) {
            return $this->matchExpression($node);
        }
        if ($node instanceof Expr\Isset_) {
            return $this->issetExpression($node, 0);
        }
        if ($node instanceof Expr\Empty_) {
            $value = $this->silent($node->expr);
            return $this->lowering->graph->emit($node, 'unary', [$value], 'Expr_BooleanNot');
        }
        if ($node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\NullsafePropertyFetch) {
            $receiver = $this->lowering->expression($node->var);
            $present = $this->lowering->graph->emit($node, 'not-null', [$receiver]);
            return $this->choice($node, $present, fn (): string => (new CallLowering($this->lowering))->nullsafe($node, $receiver), fn (): string => $this->lowering->graph->emit($node, 'constant', constant: Term::constant(null)));
        }
        return $this->lowering->graph->emit($node, 'unsupported', name: $node->getType());
    }

    /**
     * Lowers short circuit operators.
     * @param Expr\BinaryOp $node Short circuit operation
     * @return string Result register
     */
    public function binary(Expr\BinaryOp $node): string
    {
        $g = $this->lowering->graph;
        $coalesce = $node instanceof Expr\BinaryOp\Coalesce;
        $left = $coalesce ? $this->silent($node->left) : $this->lowering->expression($node->left);
        $condition = $coalesce ? $g->emit($node, 'not-null', [$left]) : $left;
        $isOr = $node instanceof Expr\BinaryOp\BooleanOr || $node instanceof Expr\BinaryOp\LogicalOr;
        $right = fn (): string => $coalesce ? $this->lowering->expression($node->right) : $g->emit($node, 'cast', [$this->lowering->expression($node->right)], 'Bool');
        $short = fn (): string => $coalesce ? $left : $g->emit($node, 'constant', constant: Term::constant($isOr));
        return $this->choice($node, $condition, $coalesce || $isOr ? $short : $right, $coalesce || $isOr ? $right : $short);
    }

    /**
     * Reads uninitialized storage without producing a warning for isset/coalesce.
     * @param Expr $node Operand
     * @param bool $existence Whether only existence is requested
     * @return string Value register
     */
    public function silent(Expr $node, bool $existence = false): string
    {
        if ($node instanceof Expr\ArrayDimFetch && !$this->lowering->addressable($node) && $node->dim !== null) {
            return $this->lowering->graph->emit($node, 'array-read', [$this->silent($node->var), $this->lowering->expression($node->dim)], attributes: ['silent' => true, 'existence' => $existence]);
        }
        return $this->lowering->addressable($node) ? $this->lowering->graph->emit($node, 'read-silent', [$this->lowering->location($node)], attributes: ['existence' => $existence]) : $this->lowering->expression($node);
    }

    /**
     * Evaluates a coalescing assignment address once.
     * @param Expr\AssignOp\Coalesce $node Assignment
     * @return string Assigned or retained value
     */
    public function coalesceAssign(Expr\AssignOp\Coalesce $node): string
    {
        $g = $this->lowering->graph;
        $location = $this->lowering->location($node->var);
        $before = $g->emit($node, 'read-silent', [$location]);
        $present = $g->emit($node, 'not-null', [$before]);
        return $this->choice($node, $present, fn (): string => $before, fn (): string => $g->emit($node, 'write', [$location, $this->lowering->expression($node->expr)]));
    }

    /**
     * Builds a common join with branch-specific SSA operands.
     * @param Expr $node Source expression
     * @param string $condition Condition register
     * @param callable(): string $yes Lowering of the true branch
     * @param callable(): string $no Lowering of the false branch
     * @return string Phi result
     */
    public function choice(Expr $node, string $condition, callable $yes, callable $no): string
    {
        $g = $this->lowering->graph;
        $true = $g->block();
        $false = $g->block();
        $join = $g->block();
        $g->end(new Terminator('branch', $condition, [$true, $false]));
        $g->current = $true;
        $a = $yes();
        $g->jump($join);
        $trueExit = $g->current;
        $g->current = $false;
        $b = $no();
        $g->jump($join);
        $falseExit = $g->current;
        $g->current = $join;
        return $g->emit($node, 'phi', [$a, $b, $condition], attributes: ['left' => $trueExit, 'right' => $falseExit]);
    }

    /**
     * Preserves ordered short circuit checks across isset operands.
     * @param Expr\Isset_ $node Existence expression
     * @param int $index Operand position
     * @return string Boolean register
     */
    public function issetExpression(Expr\Isset_ $node, int $index): string
    {
        $g = $this->lowering->graph;
        if (!isset($node->vars[$index])) {
            return $g->emit($node, 'constant', constant: Term::constant(true));
        }
        $present = $g->emit($node, 'not-null', [$this->silent($node->vars[$index], true)]);
        return $this->choice($node, $present, fn (): string => $this->issetExpression($node, $index + 1), fn (): string => $g->emit($node, 'constant', constant: Term::constant(false)));
    }

    /**
     * Lowers strict match comparisons with an explicit unmatched exception.
     * @param Expr\Match_ $node Match expression
     * @return string Selected result
     */
    public function matchExpression(Expr\Match_ $node): string
    {
        $subject = $this->lowering->expression($node->cond);
        return (new MatchLowering($this->lowering))->arms($node, $subject, 0);
    }
}
