<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use Deriver\ControlFlow\Argument;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Value\Term;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

/**
 * Coordinates parser-specific lowering without leaking AST nodes into the solver.
 * @visibility root
 */
final class Lowering
{
    /**
     * @param GraphBuilder $graph Mutable graph under construction
     * @param ProjectIndex $index Declarations and lazy callable compilation
     * @param string $symbol Owning callable
     * @param string $className Lexical class
     * @param bool $returnsByReference Whether return exposes a storage location
     */
    public function __construct(public readonly GraphBuilder $graph, public readonly ProjectIndex $index, public readonly string $symbol, public readonly string $className = '', public readonly bool $returnsByReference = false)
    {
    }

    /**
     * Lowers an expression exactly once.
     * @param Expr $expression Source expression
     * @return string Result register
     */
    public function expression(Expr $expression): string
    {
        return (new ExpressionLowering($this))->lower($expression);
    }

    /**
     * Lowers statements in source order into control-flow edges.
     * @param array<Stmt> $statements Source statements
     */
    public function statements(array $statements): void
    {
        foreach ($statements as $statement) {
            if (isset($this->graph->terminators[$this->graph->current])) {
                $this->graph->current = $this->graph->block();
            }
            (new StatementLowering($this))->lower($statement);
        }
    }

    /**
     * Obtains an address without reading it.
     * @param Expr $expression Assignable expression
     * @return string Address register
     */
    public function location(Expr $expression): string
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return $this->graph->emit($expression, 'local', name: $expression->name);
        }
        if ($expression instanceof Expr\ArrayDimFetch) {
            $parent = $this->location($expression->var);
            $key = $expression->dim === null ? '' : $this->expression($expression->dim);
            return $this->graph->emit($expression, 'element-address', [$parent, $key]);
        }
        if ($expression instanceof Expr\PropertyFetch) {
            return $this->graph->emit($expression, 'field-address', [$this->expression($expression->var), $this->name($expression->name)], name: $this->className);
        }
        if ($expression instanceof Expr\StaticPropertyFetch) {
            return $this->graph->emit($expression, 'static-address', [$this->name($expression->class), $this->name($expression->name)], name: $this->className, attributes: ['literal-class' => $expression->class instanceof Name]);
        }
        if ($expression instanceof Expr\CallLike) {
            return $this->graph->emit($expression, 'returned-address', [$this->expression($expression)]);
        }
        return $this->graph->emit($expression, 'unsupported-address', name: $expression->getType());
    }

    /**
     * Lowers a literal or dynamic name.
     * @param Node $name Name expression
     * @return string Register containing the name
     */
    public function name(Node $name): string
    {
        if ($name instanceof Name || $name instanceof Identifier) {
            $resolved = $name->getAttribute('namespacedName');
            return $this->graph->emit($name, 'constant', constant: Term::constant($resolved instanceof Name ? $resolved->toString() : $name->toString()));
        }
        if ($name instanceof Expr) {
            return $this->expression($name);
        }
        return $this->graph->emit($name, 'unsupported', name: $name->getType());
    }

    /**
     * Evaluates each argument once and retains its address for the binder.
     * @param array<Node\Arg> $arguments Call arguments
     * @param string $prepared Prepared call signature register, when available
     * @return list<Argument> Evaluated argument registers
     */
    public function arguments(array $arguments, string $prepared = ''): array
    {
        $result = [];
        foreach ($arguments as $argument) {
            $location = null;
            if ($this->addressable($argument->value)) {
                $location = $this->location($argument->value);
                $value = $prepared === '' ? $this->graph->emit($argument->value, 'read', [$location]) : $location;
            } else {
                $value = $this->expression($argument->value);
            }
            if ($prepared !== '') {
                $value = $this->graph->emit($argument->value, 'argument', [$prepared, $value], arguments: $result, attributes: ['address' => $location !== null, 'argument-name' => $argument->name?->toString(), 'unpack' => $argument->unpack, 'unpack-variable' => $argument->value instanceof Expr\Variable, 'temporary' => $argument->value instanceof Expr\CallLike]);
                $location = $value;
            }
            $result[] = new Argument($value, $argument->name?->toString(), $argument->unpack, $location);
        }
        return $result;
    }

    /**
     * Recognizes storage syntax without evaluating it.
     * @param Expr $expression Source expression
     * @return bool Whether it denotes an address
     */
    public function addressable(Expr $expression): bool
    {
        return $expression instanceof Expr\Variable || $expression instanceof Expr\ArrayDimFetch && $this->addressable($expression->var) || $expression instanceof Expr\PropertyFetch || $expression instanceof Expr\StaticPropertyFetch;
    }
}
