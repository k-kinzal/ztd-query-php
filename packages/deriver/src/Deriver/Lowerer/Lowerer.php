<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer;

use Deriver\Deriver\Lowerer\Control\GotoLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;

/**
 * Compiles function signatures, captures, and bodies to parser-independent IR.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class Lowerer
{
    /**
     * @param Index $index Captured declarations
     */
    public function __construct(public readonly Index $index)
    {
    }

    /**
     * Materializes one callable graph.
     * @param CallableSource $source Captured source body
     * @return CallableGraph Frozen control-flow graph
     */
    public function compile(CallableSource $source): CallableGraph
    {
        $g = $this->index->builder($source->path);
        $l = new LoweringScope($g, $this->index, $source->symbol, $source->className, $source->node instanceof FunctionLike && $source->node->returnsByRef());
        $node = $source->node;
        $parameters = $node instanceof FunctionLike ? $this->parameters($node, $source) : [];
        $external = $this->index->files[$source->path]->declarationsOnly;
        if ($external) {
            $g->end(new Terminator(Graph\TerminatorKind::Return, $g->emit($node, 'external-body')));
        } elseif ($node instanceof Expr\ArrowFunction) {
            $g->end(new Terminator(Graph\TerminatorKind::Return, $l->expression($node->expr)));
        } elseif ($node instanceof FunctionLike) {
            $this->promotions($node, $l);
            $l->statements($node->getStmts() ?? []);
        } elseif ($node instanceof Stmt\Namespace_) {
            $l->statements($node->stmts);
        }
        if ($g->gotos !== []) {
            (new GotoLowering($l))->resolve();
        }
        $return = $node instanceof FunctionLike ? $this->type($node->getReturnType()) : 'mixed';
        return new CallableGraph($source->symbol, $parameters, $g->finish(), $g->source($node), $return, $node instanceof FunctionLike && $node->returnsByRef(), $source->strict, $source->className, $this->captures($node), $g->regions, visibility: $node instanceof Stmt\ClassMethod ? ($node->isPrivate() ? Declaration\Metadata\Visibility::Private : ($node->isProtected() ? Declaration\Metadata\Visibility::Protected : Declaration\Metadata\Visibility::Public)) : Declaration\Metadata\Visibility::Public, static: $node instanceof Stmt\ClassMethod ? $node->isStatic() : (($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) && $node->static), abstract: $node instanceof Stmt\ClassMethod && $node->stmts === null, external: $external, docComment: $node instanceof FunctionLike ? $node->getDocComment()?->getText() ?? '' : '');
    }

    /**
     * Lowers promoted assignments before the body and after all argument validation.
     * @param FunctionLike $node Constructor declaration
     * @param LoweringScope $lowering Body graph and lexical class
     */
    public function promotions(FunctionLike $node, LoweringScope $lowering): void
    {
        $g = $lowering->graph;
        foreach ($node->getParams() as $parameter) {
            if ($parameter->flags === 0 || !$parameter->var instanceof Expr\Variable || !is_string($parameter->var->name)) {
                continue;
            }
            $receiver = $g->emit($parameter, 'read', [$g->emit($parameter, 'local', name: 'this')]);
            $name = $g->emit($parameter, 'constant', constant: Literal::constant($parameter->var->name));
            $property = $g->emit($parameter, 'field-address', [$receiver, $name], name: $lowering->className);
            $local = $lowering->location($parameter->var);
            $value = $parameter->byRef ? $local : $g->emit($parameter, 'read', [$local]);
            $g->emit($parameter, $parameter->byRef ? 'alias' : 'write', [$property, $value]);
        }
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
            $default = $parameter->default === null ? null : $this->expression($parameter->default, $source->path, $source->symbol . ':default:' . $parameter->var->name, $source->className, $source->strict);
            $type = $this->type($parameter->type);
            if ($parameter->type !== null && $parameter->default instanceof Expr\ConstFetch && strtolower($parameter->default->name->toString()) === 'null' && !in_array('null', array_map('strtolower', explode('|', $type)), true) && $type !== 'mixed') {
                $type .= '|null';
            }
            $parameters[] = new Parameter($parameter->var->name, $type, $parameter->byRef, $parameter->variadic, $default, $parameter->flags, $this->index->builder($source->path)->source($parameter));
        }
        return $parameters;
    }

    /**
     * Compiles an initialization expression as a reusable callable graph.
     * @param Expr $expression Expression AST
     * @param string $path Source path
     * @param string $symbol Synthetic callable identity
     * @param string $className Lexical class
     * @param bool $strict Scalar coercion mode of the declaring file
     * @return CallableGraph Expression graph
     */
    public function expression(Expr $expression, string $path, string $symbol, string $className = '', bool $strict = false): CallableGraph
    {
        $g = $this->index->builder($path);
        $result = (new LoweringScope($g, $this->index, $symbol, $className))->expression($expression);
        $g->end(new Terminator(Graph\TerminatorKind::Return, $result));
        return new CallableGraph($symbol, [], $g->finish(), $g->source($expression), strict: $strict, className: $className);
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
