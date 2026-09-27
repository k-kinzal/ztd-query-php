<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\Source\Compilation\EffectInspection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Selects callable owners from syntax before materializing their control-flow graphs.
 * @visibility root
 */
final class CallSiteIndex
{
    /**
     * @param ProjectIndex $index Captured declarations
     */
    public function __construct(public readonly ProjectIndex $index)
    {
    }
    /**
     * Finds possible owners of a named source invocation without compiling other bodies.
     * @param string $selector Function or method name
     * @return list<string> Deterministic callable identities
     */
    public function owners(string $selector): array
    {
        $owners = [];
        $pending = array_values($this->index->declarations);
        $seen = [];
        while ($pending !== []) {
            $source = array_shift($pending);
            if (isset($seen[$source->symbol])) {
                continue;
            }
            $seen[$source->symbol] = true;
            if ($this->contains($source->node, $selector, $source, $pending, true)) {
                $owners[$source->symbol] = true;
            }
        }
        $result = array_keys($owners);
        sort($result);
        return $result;
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
            $name = $node->name;
            if ($name instanceof Node\Name || $name instanceof Node\Identifier) {
                $resolved = $name->getAttribute('namespacedName');
                $spelling = $resolved instanceof Node\Name ? $resolved->toString() : $name->toString();
                $found = (new CallableIdentity())->key($spelling) === (new CallableIdentity())->key($selector);
            }
        }
        foreach ((new EffectInspection())->children($node) as $child) {
            $found = $this->contains($child, $selector, $source, $pending) || $found;
        }
        return $found;
    }
}
