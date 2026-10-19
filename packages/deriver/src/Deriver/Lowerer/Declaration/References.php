<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Lowerer\EffectInspection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Indexes syntax dependencies once, independently of demanded values and compiled bodies.
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class References
{
    /**
     * @var array<string, array<string, true>>
     */
    private array $calls = [];
    /**
     * @var array<string, true>
     */
    private array $lexicalConstructors = [];
    /**
     * @var array<string, array<string, true>>
     */
    private array $properties = [];
    /**
     * @var array<string, true>
     */
    private array $dynamicProperties = [];
    /**
     * @var array<string, array<string, true>>
     */
    private array $closures = [];
    /**
     * @var array<string, array<string, true>>
     */
    private array $globals = [];
    /**
     * @var array<string, true>
     */
    private array $dynamicGlobals = [];
    /**
     * @var array<string, true>
     */
    private array $indexed = [];

    /**
     * Uses declarations after trait composition, without lowering their bodies.
     */
    public function __construct(private readonly Index $index)
    {
    }

    /**
     * Registers each lexical body once, including declarations discovered during lowering.
     */
    public function add(CallableSource $source): void
    {
        if (isset($this->indexed[$source->symbol])) {
            return;
        }
        $this->indexed[$source->symbol] = true;
        $pending = [$source->node];
        $inspection = new EffectInspection();
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node !== $source->node && $this->nested($node, $source)) {
                continue;
            }
            $this->call($node, $source->symbol);
            $this->property($node, $source->symbol);
            if (str_starts_with($source->symbol, 'script:') && $node instanceof Expr\Variable) {
                if (is_string($node->name)) {
                    $this->globals[$node->name][$source->symbol] = true;
                } else {
                    $this->dynamicGlobals[$source->symbol] = true;
                }
            }
            array_push($pending, ...array_reverse($inspection->children($node)));
        }
    }

    /**
     * Keeps nested lexical owners separate from their enclosing body.
     */
    public function nested(Node $node, CallableSource $source): bool
    {
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $symbol = $this->index->registerClosure($node, $source->path, $source->className);
            $this->closures[$symbol][$source->symbol] = true;
            return true;
        }
        return $node instanceof Stmt\Function_ || $node instanceof Stmt\ClassLike;
    }

    /**
     * Records qualified names, namespace fallbacks and lexical constructor candidates.
     */
    public function call(Node $node, string $owner): void
    {
        $identity = new CallableIdentity();
        if ($node instanceof Expr\FuncCall || $node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall) {
            $this->calls['*'][$owner] = true;
            $name = $node->name;
            if (!$name instanceof Node\Name && !$name instanceof Node\Identifier) {
                $this->calls[''][$owner] = true;
            }
            if ($name instanceof Node\Name || $name instanceof Node\Identifier) {
                $resolved = $name->getAttribute('namespacedName');
                $this->calls[$identity->key($resolved instanceof Node\Name ? $resolved->toString() : $name->toString())][$owner] = true;
                if ($node instanceof Expr\FuncCall && $name instanceof Node\Name && $name->isUnqualified()) {
                    $this->calls[$identity->key($name->toString())][$owner] = true;
                }
                if ($node instanceof Expr\StaticCall && $node->class instanceof Node\Name && strcasecmp($name->toString(), '__construct') === 0) {
                    if ($node->class->isSpecialClassName()) {
                        $this->lexicalConstructors[$owner] = true;
                    } else {
                        $this->calls[$identity->key($node->class->toString() . '::__construct')][$owner] = true;
                    }
                }
            }
        } elseif ($node instanceof Expr\New_ && $node->class instanceof Node\Name) {
            $this->calls['*'][$owner] = true;
            $this->calls[$identity->key($node->class->toString() . '::__construct')][$owner] = true;
            if ($node->class->isSpecialClassName()) {
                $this->lexicalConstructors[$owner] = true;
            }
        }
    }

    /**
     * Records promoted properties and every mutation shape recognized by property discovery.
     */
    public function property(Node $node, string $owner): void
    {
        if ($node instanceof Node\Param && $node->flags !== 0 && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $this->properties[$node->var->name][$owner] = true;
        }
        if (!(new PropertyWriteIndex($this->index))->mutation($node)) {
            return;
        }
        $target = $node->var;
        while ($target instanceof Expr\ArrayDimFetch) {
            $target = $target->var;
        }
        if ($target instanceof Expr\PropertyFetch || $target instanceof Expr\StaticPropertyFetch) {
            if ($target->name instanceof Node\Identifier) {
                $this->properties[$target->name->toString()][$owner] = true;
            } else {
                $this->dynamicProperties[$owner] = true;
            }
        }
    }

    /**
     * @return list<string> Owners of matching calls, in source identity order
     */
    public function calls(string $selector): array
    {
        $key = (new CallableIdentity())->key($selector);
        $owners = $this->calls[$key] ?? [];
        $symbols = array_keys($owners + (str_ends_with(strtolower($selector), '::__construct') ? $this->lexicalConstructors : []));
        sort($symbols, SORT_STRING);
        return $symbols;
    }

    /**
     * @return list<string> Possible writers, including dynamic property names
     */
    public function properties(string $name): array
    {
        $symbols = array_keys(($this->properties[$name] ?? []) + $this->dynamicProperties);
        sort($symbols, SORT_STRING);
        return $symbols;
    }

    /**
     * @return list<string> Lexical owners of the selected closure allocation
     */
    public function closures(string $symbol): array
    {
        $symbols = array_keys($this->closures[$symbol] ?? []);
        sort($symbols, SORT_STRING);
        return $symbols;
    }

    /**
     * @return list<string> Scripts which may define the requested global
     */
    public function globals(string $name): array
    {
        $symbols = array_keys(($this->globals[$name] ?? []) + $this->dynamicGlobals);
        sort($symbols, SORT_STRING);
        return $symbols;
    }
}
