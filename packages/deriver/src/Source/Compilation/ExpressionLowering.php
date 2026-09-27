<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use Deriver\Source\Compilation\Control\ConditionalLowering;
use Deriver\Value\Term;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Dispatches expression lowering to operations with explicit effects.
 * @visibility root
 */
final class ExpressionLowering
{
    /**
     * @param Lowering $lowering Current callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Emits the evaluated expression or an explicit language boundary.
     * @param Expr $node Source expression
     * @return string Result register
     */
    public function lower(Expr $node): string
    {
        $g = $this->lowering->graph;
        if ($node instanceof Scalar\String_ || $node instanceof Scalar\Int_ || $node instanceof Scalar\Float_) {
            return $g->emit($node, 'constant', constant: Term::constant($node->value));
        }
        if ($node instanceof Expr\ConstFetch) {
            return $g->emit($node, 'constant-fetch', name: $node->name->toString());
        }
        if ($node instanceof Scalar\MagicConst) {
            return $this->magic($node);
        }
        return $this->operation($node);
    }

    /**
     * Lowers source lexical names independently of internal default and closure graph names.
     * @param Scalar\MagicConst $node Source magic constant
     * @return string Constant or source-dependent register
     */
    public function magic(Scalar\MagicConst $node): string
    {
        $scope = $node->getAttribute('deriver-lexical');
        $key = match ($node->getName()) {
            '__FUNCTION__' => 'function', '__METHOD__' => 'method', '__NAMESPACE__' => 'namespace', default => '',
        };
        if ($key !== '' && is_array($scope) && is_string($scope[$key] ?? null)) {
            return $this->lowering->graph->emit($node, 'constant', constant: Term::constant($scope[$key]));
        }
        return $this->lowering->graph->emit($node, 'magic-constant', name: $node->getName());
    }

    /**
     * Dispatches effectful, conditional, and aggregate expressions after literals.
     * @param Expr $node Non-literal expression
     * @return string Result register
     */
    public function operation(Expr $node): string
    {
        $g = $this->lowering->graph;
        if ($this->lowering->addressable($node)) {
            return $g->emit($node, 'read', [$this->lowering->location($node)]);
        }
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignRef || $node instanceof Expr\AssignOp || $node instanceof Expr\PreInc || $node instanceof Expr\PostInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec) {
            return (new AssignmentLowering($this->lowering))->lower($node);
        }
        if ($node instanceof Expr\Ternary || $node instanceof Expr\Match_ || $node instanceof Expr\Isset_ || $node instanceof Expr\Empty_ || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\NullsafePropertyFetch) {
            return (new ConditionalLowering($this->lowering))->lower($node);
        }
        if ($node instanceof Expr\BinaryOp) {
            return $this->binary($node);
        }
        return $this->computed($node);
    }

    /**
     * Preserves short circuiting and flags expressions with uncertain effect order.
     * @param Expr\BinaryOp $node Binary expression
     * @return string Result register
     */
    public function binary(Expr\BinaryOp $node): string
    {
        if ($node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\BooleanOr || $node instanceof Expr\BinaryOp\LogicalAnd || $node instanceof Expr\BinaryOp\LogicalOr || $node instanceof Expr\BinaryOp\Coalesce) {
            return (new ConditionalLowering($this->lowering))->binary($node);
        }
        $left = $this->lowering->expression($node->left);
        $right = $this->lowering->expression($node->right);
        $result = $this->lowering->graph->emit($node, 'binary', [$left, $right], $node->getOperatorSigil());
        if ((new EffectInspection())->conflicts($node->left, $node->right)) {
            return $this->lowering->graph->emit($node, 'uncertain-order', [$result]);
        }
        return $result;
    }

    /**
     * Lowers unary, cast, clone, constant, and exceptional expressions.
     * @param Expr $node Source expression
     * @return string Result register
     */
    public function other(Expr $node): string
    {
        $g = $this->lowering->graph;
        if ($node instanceof Expr\Cast) {
            return $g->emit($node, 'cast', [$this->lowering->expression($node->expr)], substr($node->getType(), 10));
        }
        if ($node instanceof Expr\BooleanNot || $node instanceof Expr\UnaryMinus || $node instanceof Expr\UnaryPlus || $node instanceof Expr\BitwiseNot) {
            return $g->emit($node, 'unary', [$this->lowering->expression($node->expr)], $node->getType());
        }
        if ($node instanceof Expr\Clone_) {
            return $g->emit($node, 'clone', [$this->lowering->expression($node->expr)]);
        }
        if ($node instanceof Expr\Throw_) {
            return $g->emit($node, 'throw', [$this->lowering->expression($node->expr)]);
        }
        if ($node instanceof Expr\ClassConstFetch) {
            return $g->emit($node, 'class-constant', [$this->lowering->name($node->class), $this->lowering->name($node->name)], $this->lowering->className, attributes: ['literal-class' => $node->class instanceof \PhpParser\Node\Name, 'class-name' => $node->name instanceof \PhpParser\Node\Identifier && strtolower($node->name->toString()) === 'class']);
        }
        if ($node instanceof Expr\Instanceof_) {
            return $g->emit($node, 'instanceof', [$this->lowering->expression($node->expr), $this->lowering->name($node->class)], attributes: ['literal-class' => $node->class instanceof \PhpParser\Node\Name]);
        }
        if ($node instanceof Expr\Include_ || $node instanceof Expr\Eval_) {
            return $g->emit($node, 'symbol-table-boundary', [$this->lowering->expression($node->expr)], $node instanceof Expr\Include_ ? 'INCLUDE_SEMANTICS_UNSUPPORTED' : 'UNSUPPORTED_LANGUAGE_FEATURE');
        }
        return $g->emit($node, 'unsupported', name: $node->getType());
    }

    /**
     * Dispatches calls and aggregate expressions after storage and control expressions.
     * @param Expr $node Computed expression
     * @return string Result register
     */
    public function computed(Expr $node): string
    {
        if ($node instanceof Expr\ArrayDimFetch && $node->dim !== null) {
            return $this->lowering->graph->emit($node, 'array-read', [$this->lowering->expression($node->var), $this->lowering->expression($node->dim)]);
        }
        if ($node instanceof Expr\FuncCall || $node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall || $node instanceof Expr\New_ || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            return (new CallLowering($this->lowering))->lower($node);
        }
        if ($node instanceof Expr\Array_ || $node instanceof Scalar\InterpolatedString) {
            return (new AggregateLowering($this->lowering))->lower($node);
        }
        return $this->other($node);
    }
}
