<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Transfer;

use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Captures callable identity and lexical values without executing closure bodies.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class CallableTransfer
{
    /**
     * @param Context $context Declaration and evaluator world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Evaluates callable creation and class-aware pure operations.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction Operation
     * @param State $state Lexical state
     * @return Term Evaluated identity
     */
    public function evaluate(CallableGraph $callable, Instruction $instruction, State $state): Term
    {
        if ($instruction->operation === 'closure') {
            return $this->closure($instruction, $state);
        }
        $a = $state->value($instruction->operands[0] ?? '');
        $b = $state->value($instruction->operands[1] ?? '');
        if ($instruction->operation === 'instanceof') {
            return $this->instance($callable, $state, $a, $b, ($instruction->attributes['literal-class'] ?? true) === true);
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::from($instruction->operation), operands: [$a, $b], attributes: ['type' => 'bool']);
    }

    /**
     * Captures values now and cells by reference for later invocation.
     * @param Instruction $instruction Closure creation
     * @param State $state Lexical memory
     * @return Term Captured closure
     */
    public function closure(Instruction $instruction, State $state): Term
    {
        $body = $this->context->program->callable($instruction->name);
        $captures = [];
        foreach ($body->captures ?? [] as $name => $reference) {
            $location = $state->local($name);
            $captures[$name] = $reference ? new Term(\Deriver\Deriver\Evaluator\TermKind::Cell, $state->memory->reference($location)) : $state->memory->read($location);
        }
        if ($body?->static !== true && isset($state->locals['this'])) {
            $captures['this'] = $state->memory->read($state->locals['this']);
        }
        if ($body?->static === true) {
            unset($captures['this']);
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Closure, $instruction->name, $captures, ['identity' => $state->memory->fresh('closure'), 'late-static' => $state->lateStaticClass]);
    }
    /**
     * Applies known runtime class relations to objects, enums, closures, and scalar values.
     * @param CallableGraph $caller Lexical class context
     * @param State $state Called class context
     * @param Term $value Tested value
     * @param Term $bound Target class name
     * @param bool $literal Whether the tested class uses literal syntax
     * @return Term Known relation or a symbolic instance predicate
     */
    public function instance(CallableGraph $caller, State $state, Term $value, Term $bound, bool $literal = true): Term
    {
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Array], true)) {
            return Term::constant(false);
        }
        $names = new ClassNames($this->context);
        $class = $names->object($value);
        $target = $names->object($bound) ?? ($bound->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($bound->literal) ? $bound->literal : null);
        if ($class !== null && $target === null && in_array($bound->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Array, \Deriver\Deriver\Evaluator\TermKind::Uninitialized], true)) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error');
        }
        if ($class !== null && $target !== null) {
            $dispatch = new Dispatch($this->context->program);
            $target = $literal ? $dispatch->className($target, $caller->className, $state->lateStaticClass) : ltrim($target, '\\');
            return $literal && $target === '' ? new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error') : Term::constant($dispatch->subtype($class, $target));
        }
        return new Term(\Deriver\Deriver\Evaluator\TermKind::Instanceof, operands: [$value, $bound], attributes: ['type' => 'bool']);
    }
}
