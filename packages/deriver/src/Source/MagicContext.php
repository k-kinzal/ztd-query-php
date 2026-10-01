<?php

declare(strict_types=1);

namespace Deriver\Source;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Captures lexical magic names before closures and initializers become separate graphs.
 * @visibility root
 */
final class MagicContext extends NodeVisitorAbstract
{
    /**
     * @var array{namespace: string, class: string, function: string, method: string} Current lexical names.
     */
    public array $scope = ['namespace' => '', 'class' => '', 'function' => '', 'method' => ''];
    /**
     * @var list<array{namespace: string, class: string, function: string, method: string}> Nested lexical scopes.
     */
    public array $stack = [];

    /**
     * Saves declaration names without relying on internal callable identifiers.
     * @param Node $node Syntax being entered
     * @return null Continue traversing all nested declarations
     */
    #[Override]
    public function enterNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_) {
            $this->stack[] = $this->scope;
            $this->scope['namespace'] = $node->name?->toString() ?? '';
        } elseif ($node instanceof Stmt\ClassLike) {
            $this->stack[] = $this->scope;
            $this->scope['class'] = ($node->namespacedName ?? null)?->toString() ?? '';
        } elseif ($node instanceof Node\FunctionLike) {
            $this->stack[] = $this->scope;
            $function = $node instanceof Stmt\Function_ ? ($node->namespacedName ?? null)?->toString() ?? $node->name->toString() : '';
            if ($node instanceof Stmt\ClassMethod) {
                $function = $node->name->toString();
            } elseif ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
                $function = ($this->scope['namespace'] === '' ? '' : $this->scope['namespace'] . '\\') . '{closure}';
            }
            $this->scope['function'] = $function;
            $this->scope['method'] = $node instanceof Stmt\ClassMethod ? $this->scope['class'] . '::' . $function : $function;
        }
        if ($node instanceof Scalar\MagicConst) {
            $node->setAttribute('deriver-lexical', $this->scope);
        }
        return null;
    }

    /**
     * Restores the enclosing declaration after a nested callable or namespace.
     * @param Node $node Completed syntax
     * @return null Preserve the original syntax node
     */
    #[Override]
    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\Namespace_ || $node instanceof Stmt\ClassLike || $node instanceof Node\FunctionLike) {
            $previous = array_pop($this->stack);
            if ($previous !== null) {
                $this->scope = $previous;
            }
        }
        return null;
    }
}
