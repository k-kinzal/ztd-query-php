<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Transfer;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\Dispatch;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Captures callable identity and lexical values without executing closure bodies.
 * @visibility root
 */
final class CallableTransfer
{
    /**
     * @param Context $context Declaration and model world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Evaluates callable creation and class-aware pure operations.
     * @param CallableIR $callable Current callable
     * @param Instruction $instruction Operation
     * @param State $state Lexical state
     * @return Term Evaluated identity
     */
    public function evaluate(CallableIR $callable, Instruction $instruction, State $state): Term
    {
        if ($instruction->operation === 'closure') {
            return $this->closure($instruction, $state);
        }
        $a = $state->value($instruction->operands[0] ?? '');
        $b = $state->value($instruction->operands[1] ?? '');
        $dispatch = new Dispatch($this->context->program);
        if ($instruction->operation === 'class-constant' && is_string($a->literal) && is_string($b->literal)) {
            $class = $dispatch->className($a->literal, $callable->className, $state->lateStaticClass);
            if (strtolower($b->literal) === 'class') {
                return Term::constant($class);
            }
            return $this->context->program->classes()[strtolower($class)]->constants[$b->literal] ?? $this->context->frontier('INCOMPLETE_SOURCE', $instruction->source, $class . '::' . $b->literal);
        }
        if ($instruction->operation === 'instanceof') {
            return $this->instance($callable, $state, $a, $b);
        }
        return new Term($instruction->operation, operands: [$a, $b], attributes: ['type' => 'bool']);
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
            $captures[$name] = $reference ? new Term('cell', $state->memory->reference($location)) : $state->memory->read($location);
        }
        if ($body?->static !== true && isset($state->locals['this'])) {
            $captures['this'] = $state->memory->read($state->locals['this']);
        }
        if ($body?->static === true) {
            unset($captures['this']);
        }
        return new Term('closure', $instruction->name, $captures, ['identity' => $state->memory->fresh('closure'), 'late-static' => $state->lateStaticClass]);
    }
    /**
     * Applies known runtime class relations to objects, enums, closures, and scalar values.
     * @param CallableIR $caller Lexical class context
     * @param State $state Called class context
     * @param Term $value Tested value
     * @param Term $bound Target class name
     * @return Term Known relation or a symbolic instance predicate
     */
    public function instance(CallableIR $caller, State $state, Term $value, Term $bound): Term
    {
        if (in_array($value->kind, ['constant', 'array'], true)) {
            return Term::constant(false);
        }
        $class = $value->kind === 'closure' ? 'Closure' : ($value->attributes['class'] ?? null);
        if (is_string($class) && is_string($bound->literal)) {
            $dispatch = new Dispatch($this->context->program);
            return Term::constant($dispatch->subtype($class, $dispatch->className($bound->literal, $caller->className, $state->lateStaticClass)));
        }
        return new Term('instanceof', operands: [$value, $bound], attributes: ['type' => 'bool']);
    }
}
