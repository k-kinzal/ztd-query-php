<?php

declare(strict_types=1);

namespace Deriver\Internal\Frontend\Php;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Parameter;
use Deriver\Internal\IR\Terminator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;

/**
 * Compiles function signatures, captures, and bodies to parser-independent IR.
 * @visibility root
 */
final class CallableCompiler
{
    /**
     * @param ProjectIndex $index Captured declarations
     */
    public function __construct(public readonly ProjectIndex $index)
    {
    }

    /**
     * Materializes one callable graph.
     * @param CallableSource $source Captured source body
     * @return CallableIR Frozen control-flow graph
     */
    public function compile(CallableSource $source): CallableIR
    {
        $g = $this->index->builder($source->path);
        $l = new Lowering($g, $this->index, $source->symbol, $source->className, $source->node instanceof FunctionLike && $source->node->returnsByRef());
        $node = $source->node;
        $parameters = $node instanceof FunctionLike ? $this->parameters($node, $source) : [];
        $external = $this->index->files[$source->path]->declarationsOnly;
        if ($external) {
            $g->end(new Terminator('return', $g->emit($node, 'external-body')));
        } elseif ($node instanceof Expr\ArrowFunction) {
            $g->end(new Terminator('return', $l->expression($node->expr)));
        } elseif ($node instanceof FunctionLike) {
            $l->statements($node->getStmts() ?? []);
        } elseif ($node instanceof Stmt\Namespace_) {
            $l->statements($node->stmts);
        }
        $return = $node instanceof FunctionLike ? $this->type($node->getReturnType()) : 'mixed';
        return new CallableIR($source->symbol, $parameters, $g->finish(), $g->source($node), $return, $node instanceof FunctionLike && $node->returnsByRef(), $source->strict, $source->className, $this->captures($node), $g->regions, visibility: $node instanceof Stmt\ClassMethod ? ($node->isPrivate() ? 'private' : ($node->isProtected() ? 'protected' : 'public')) : 'public', static: $node instanceof Stmt\ClassMethod ? $node->isStatic() : (($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) && $node->static), abstract: $node instanceof Stmt\ClassMethod && $node->stmts === null, external: $external);
    }

    /**
     * Builds default-expression graphs without evaluating their values.
     * @param FunctionLike $node Function declaration
     * @param CallableSource $source Captured context
     * @return list<Parameter> Signature parameters
     */
    public function parameters(FunctionLike $node, CallableSource $source): array
    {
        $parameters = [];
        foreach ($node->getParams() as $parameter) {
            if (!$parameter->var instanceof Expr\Variable || !is_string($parameter->var->name)) {
                continue;
            }
            $default = $parameter->default === null ? null : $this->expression($parameter->default, $source->path, $source->symbol . ':default:' . $parameter->var->name, $source->className);
            $parameters[] = new Parameter($parameter->var->name, $this->type($parameter->type), $parameter->byRef, $parameter->variadic, $default, $parameter->flags);
        }
        return $parameters;
    }

    /**
     * Compiles an initialization expression as a reusable callable graph.
     * @param Expr $expression Expression AST
     * @param string $path Source path
     * @param string $symbol Synthetic callable identity
     * @param string $className Lexical class
     * @return CallableIR Expression graph
     */
    public function expression(Expr $expression, string $path, string $symbol, string $className = ''): CallableIR
    {
        $g = $this->index->builder($path);
        $result = (new Lowering($g, $this->index, $symbol, $className))->expression($expression);
        $g->end(new Terminator('return', $result));
        return new CallableIR($symbol, [], $g->finish(), $g->source($expression), className: $className);
    }

    /**
     * Preserves PHP declaration type spelling after name resolution.
     * @param Node|null $type Declaration type
     * @return string Normalized type expression
     */
    public function type(?Node $type): string
    {
        if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
            return $type->toString();
        }
        if ($type instanceof Node\NullableType) {
            return $this->type($type->type) . '|null';
        }
        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            return implode($type instanceof Node\UnionType ? '|' : '&', array_map(fn (Node $part): string => $this->type($part), $type->types));
        }
        return 'mixed';
    }

    /**
     * Computes closure capture modes; arrows snapshot free variables at creation.
     * @param Node $node Callable node
     * @return array<string, bool> Capture name to reference mode
     */
    public function captures(Node $node): array
    {
        $captures = [];
        if ($node instanceof Expr\Closure) {
            foreach ($node->uses as $use) {
                if (is_string($use->var->name)) {
                    $captures[$use->var->name] = $use->byRef;
                }
            }
        }
        if ($node instanceof Expr\ArrowFunction) {
            $captures = $this->freeVariables($node->expr);
            foreach ($node->params as $parameter) {
                if ($parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                    unset($captures[$parameter->var->name]);
                }
            }
        }
        return $captures;
    }

    /**
     * Collects lexical reads without executing nested closures.
     * @param Node $node Expression subtree
     * @return array<string, bool> Captured variables
     */
    public function freeVariables(Node $node): array
    {
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            return [$node->name => false];
        }
        $variables = [];
        foreach ((new EffectInspection())->children($node) as $child) {
            $variables += $this->freeVariables($child);
        }
        return $variables;
    }
}
