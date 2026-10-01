<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

/**
 * Lowers source calls and closure creation to the common invocation protocol.
 * @visibility root
 */
final class CallLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Emits closure allocation, first-class callables, or invocation.
     * @param Expr $node Callable expression
     * @return string Result register
     */
    public function lower(Expr $node): string
    {
        $l = $this->lowering;
        $g = $l->graph;
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $symbol = $l->index->registerClosure($node, $g->path, $l->className);
            return $g->emit($node, 'closure', name: $symbol);
        }
        if ($node instanceof Expr\New_) {
            if ($node->class instanceof Stmt\Class_) {
                return $g->emit($node, 'unsupported', name: 'anonymous-class');
            }
            return $this->invoke($node, 'new', [$l->name($node->class)], ['scope' => $l->className, 'literal-class' => $node->class instanceof Name]);
        }
        if ($node instanceof Expr\FuncCall) {
            $target = $l->name($node->name);
            $fallback = $node->name instanceof Name && !$node->name->isFullyQualified() ? $node->name->getLast() : '';
            return $node->isFirstClassCallable() ? $g->emit($node, 'callable', [$target], attributes: ['fallback' => $fallback]) : $this->invoke($node, 'invoke', [$target], ['fallback' => $fallback]);
        }
        if ($node instanceof Expr\MethodCall || $node instanceof Expr\StaticCall) {
            $receiver = $node instanceof Expr\MethodCall ? $l->expression($node->var) : $l->name($node->class);
            $name = $l->name($node->name);
            $operation = $node instanceof Expr\MethodCall ? 'invoke-method' : 'invoke-static';
            $attributes = ['scope' => $l->className, 'static' => $node instanceof Expr\StaticCall, 'literal-class' => $node instanceof Expr\StaticCall && $node->class instanceof Name];
            return $node->isFirstClassCallable() ? $g->emit($node, 'callable-method', [$receiver, $name], attributes: $attributes) : $this->invoke($node, $operation, [$receiver, $name], $attributes);
        }
        return $g->emit($node, 'unsupported', name: $node->getType());
    }

    /**
     * Lowers the non-null branch after evaluating the receiver once.
     * @param Expr\NullsafeMethodCall|Expr\NullsafePropertyFetch $node Nullsafe expression
     * @param string $receiver Evaluated receiver
     * @return string Non-null result
     */
    public function nullsafe(Expr\NullsafeMethodCall|Expr\NullsafePropertyFetch $node, string $receiver): string
    {
        $l = $this->lowering;
        $name = $l->name($node->name);
        if ($node instanceof Expr\NullsafeMethodCall) {
            return $this->invoke($node, 'invoke-method', [$receiver, $name], ['scope' => $l->className]);
        }
        $address = $l->graph->emit($node, 'field-address', [$receiver, $name], name: $l->className);
        return $l->graph->emit($node, 'read', [$address]);
    }

    /**
     * Resolves a call before evaluating and freezing its arguments in source order.
     * @param Expr\CallLike $node Invocation syntax
     * @param string $operation Call operation
     * @param list<string> $operands Evaluated receiver and target registers
     * @param array<string, scalar|null> $attributes Lexical call metadata
     * @return string Invocation result register
     */
    public function invoke(Expr\CallLike $node, string $operation, array $operands, array $attributes): string
    {
        $prepared = $this->lowering->graph->emit($node, 'call-prepare', $operands, attributes: [...$attributes, 'call-operation' => $operation]);
        $arguments = $this->lowering->arguments($node->getArgs(), $prepared);
        return $this->lowering->graph->emit($node, $operation, $operands, arguments: $arguments, attributes: [...$attributes, 'prepared' => $prepared]);
    }
}
