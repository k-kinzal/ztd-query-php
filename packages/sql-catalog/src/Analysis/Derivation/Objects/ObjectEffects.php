<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation\Objects;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\FreeNames;
use SqlCatalog\Php\ParsedFile;
use WeakMap;

/**
 * Conservative alias dependencies for calls that may mutate an object.
 *
 * @visibility root
 */
final class ObjectEffects
{
    /**
     * @var WeakMap<Node, array<string, array<string, true>>>
     */
    private WeakMap $aliases;

    /**
     * Indexes possible file-scope aliases; function scopes are indexed on demand.
     *
     * @param list<ParsedFile> $files
     */
    public function __construct(array $files = [])
    {
        $this->aliases = new WeakMap();
        foreach ($files as $file) {
            $graph = $this->graph(new Stmt\Namespace_(null, $file->statements));
            foreach ($file->statements as $statement) {
                if (!$statement instanceof Node\FunctionLike && !$statement instanceof Stmt\ClassLike) {
                    $this->aliases[$statement] = $graph;
                }
            }
        }
    }

    /**
     * @return array<string, true> Names whose objects a call may change.
     */
    public function writes(Node $node): array
    {
        if (!$node instanceof Expr\CallLike && !$node instanceof Expr\PropertyFetch) {
            return [];
        }
        if ($node instanceof Expr\CallLike && $node->isFirstClassCallable()) {
            return [];
        }
        $roots = [];
        if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\PropertyFetch) {
            $root = $this->root($node->var);
            if ($root !== null) {
                $roots[$root] = true;
            }
        }
        foreach ($node instanceof Expr\CallLike ? $node->getArgs() : [] as $argument) {
            foreach ($this->storedRoots($argument->value) as $root) {
                $roots[$root] = true;
            }
            if ($argument->value instanceof Expr\Closure || $argument->value instanceof Expr\ArrowFunction) {
                $roots += (new FreeNames())->read($argument->value);
            }
        }
        $owner = $this->owner($node);
        $aliases = $this->aliases[$owner] ?? $this->graph($owner);
        $this->aliases[$owner] = $aliases;
        $queue = array_keys($roots);
        while ($queue !== []) {
            foreach ($aliases[array_shift($queue)] ?? [] as $alias => $_) {
                if (!isset($roots[$alias])) {
                    $roots[$alias] = true;
                    $queue[] = $alias;
                }
            }
        }

        return $roots;
    }

    /**
     * The variable through which an expression reaches an object.
     */
    public function root(Node $node): ?string
    {
        if ($node instanceof Expr\Variable) {
            return is_string($node->name) ? $node->name : null;
        }
        if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\ArrayDimFetch) {
            return $this->root($node->var);
        }

        return (new FreeNames())->propertyName($node);
    }

    /**
     * The lexical body in which alias assignments are relevant.
     */
    public function owner(Node $node): Node
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            $node = $parent;
            if ($node instanceof Node\FunctionLike || $node instanceof Stmt\Namespace_) {
                break;
            }
            $parent = $node->getAttribute('parent');
        }

        return $node;
    }

    /**
     * @return array<string, array<string, true>> Possible aliases, without claiming equal runtime identity.
     */
    public function graph(Node $owner): array
    {
        $graph = [];
        foreach ($this->assignments($owner, true) as $assignment) {
            $left = $this->root($assignment->var);
            foreach ($this->storedRoots($assignment->expr) as $right) {
                if ($left !== null) {
                    $graph[$left][$right] = true;
                    $graph[$right][$left] = true;
                }
            }
        }

        return $graph;
    }

    /**
     * Possible object references stored by an assignment, including array elements.
     *
     * @return list<string>
     */
    public function storedRoots(Expr $expression): array
    {
        $root = $this->root($expression);
        if ($root !== null) {
            return [$root];
        }
        $roots = [];
        if ($expression instanceof Expr\Array_) {
            foreach ($expression->items as $item) {
                $roots = array_merge($roots, $this->storedRoots($item->value));
            }
        }

        return $roots;
    }

    /**
     * @return list<Expr\Assign|Expr\AssignRef> Assignments outside nested declarations.
     */
    public function assignments(Node $node, bool $root = false): array
    {
        if (!$root && ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike)) {
            return [];
        }
        $found = $node instanceof Expr\Assign || $node instanceof Expr\AssignRef ? [$node] : [];
        foreach (get_object_vars($node) as $sub) {
            foreach (is_array($sub) ? $sub : [$sub] as $child) {
                if ($child instanceof Node) {
                    $found = array_merge($found, $this->assignments($child));
                }
            }
        }

        return $found;
    }
}
