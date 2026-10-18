<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Lowerer\EffectInspection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Selects callable owners from syntax before materializing their control-flow graphs.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class CallSiteIndex
{
    /**
     * @param Index $index Captured declarations
     */
    public function __construct(public readonly Index $index)
    {
    }
    /**
     * Finds possible owners of a named source invocation without compiling other bodies.
     * @param string $selector Function or method name
     * @return list<string> Deterministic callable identities
     */
    public function owners(string $selector): array
    {
        return $this->index->callOwners($selector);
    }
    /**
     * Inspects a lexical body and indexes nested closures separately.
     * @param Node $node Current syntax node
     * @param string $selector Requested invocation spelling
     * @param CallableSource $source Lexical context
     * @param list<CallableSource> $pending Nested callable owners
     * @param bool $root Whether this is the owning declaration
     * @return bool Whether this body may contain the selected call
     */
    public function contains(Node $node, string $selector, CallableSource $source, array &$pending, bool $root = false): bool
    {
        if (!$root && ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction)) {
            $symbol = $this->index->registerClosure($node, $source->path, $source->className);
            $pending[] = $this->index->declarations[(new CallableIdentity())->key($symbol)];
            return false;
        }
        if (!$root && ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassLike)) {
            return false;
        }
        $found = false;
        if ($node instanceof Expr\FuncCall || $node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall) {
            $found = $selector === '*';
            $name = $node->name;
            if ($name instanceof Node\Name || $name instanceof Node\Identifier) {
                $resolved = $name->getAttribute('namespacedName');
                $spelling = $resolved instanceof Node\Name ? $resolved->toString() : $name->toString();
                $found = $selector === '*' || (new CallableIdentity())->key($spelling) === (new CallableIdentity())->key($selector) || $this->fallback($node, $selector);
            }
        } elseif ($node instanceof Expr\New_ && $node->class instanceof Node\Name) {
            $found = $this->creation($node->class, $selector);
        }
        foreach ((new EffectInspection())->children($node) as $child) {
            $found = $this->contains($child, $selector, $source, $pending) || $found;
        }
        return $found;
    }
    /**
     * Matches object creation against `*` or a `Class::__construct` selector, keeping `self`, `parent`, and `static` for the compiled check.
     * @param Node\Name $class Created class as written, resolved by the name resolver
     * @param string $selector Requested invocation spelling
     * @return bool Whether this creation may be selected
     */
    public function creation(Node\Name $class, string $selector): bool
    {
        $identity = new CallableIdentity();
        return $selector === '*' || $class->isSpecialClassName() && str_ends_with(strtolower($selector), '::__construct') || $identity->key($class->toString() . '::__construct') === $identity->key($selector);
    }

    /**
     * Matches an unqualified function spelling that may fall back to the global namespace.
     * @param Node $node Possible invocation
     * @param string $selector Requested global function
     * @return bool Whether namespace fallback can select this name
     */
    public function fallback(Node $node, string $selector): bool
    {
        return $node instanceof Expr\FuncCall && $node->name instanceof Node\Name && $node->name->isUnqualified() && (new CallableIdentity())->key($node->name->toString()) === (new CallableIdentity())->key($selector);
    }

}
