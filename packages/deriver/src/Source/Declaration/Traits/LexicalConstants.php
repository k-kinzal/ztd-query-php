<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration\Traits;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Freezes trait-origin magic names while allowing self and __CLASS__ to bind to consumers.
 * @visibility root
 */
final class LexicalConstants extends NodeVisitorAbstract
{
    /**
     * @var list<string> Nested source function names
     */
    public array $functions = [];

    /**
     * @param string $trait Original trait namespace and name
     */
    public function __construct(public readonly string $trait)
    {
    }

    /**
     * Tracks function boundaries while traversing a copied trait member.
     * @param Node $node Copied syntax node
     * @return null Traversal continues
     */
    #[Override]
    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_) {
            $this->functions[] = $node->name->toString();
        } elseif ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $this->functions[] = '{closure}';
        }
        return null;
    }

    /**
     * Replaces only names tied to the original trait declaration.
     * @param Node $node Copied syntax node
     * @return Node|null A literal replacement or unchanged syntax
     */
    #[Override]
    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof FunctionLike) {
            array_pop($this->functions);
        }
        if (!$node instanceof Scalar\MagicConst) {
            return null;
        }
        $function = $this->functions[array_key_last($this->functions) ?? 0] ?? '';
        $separator = strrpos($this->trait, '\\');
        $namespace = $separator === false ? '' : substr($this->trait, 0, $separator);
        $closure = $namespace === '' ? '{closure}' : $namespace . '\\{closure}';
        $value = match ($node->getName()) {
            '__TRAIT__' => $this->trait,
            '__METHOD__' => $function === '{closure}' ? $closure : $this->trait . '::' . $function,
            '__FUNCTION__' => $function === '{closure}' ? $closure : $function,
            default => null,
        };
        return $value === null ? null : new Scalar\String_($value, $node->getAttributes());
    }
}
