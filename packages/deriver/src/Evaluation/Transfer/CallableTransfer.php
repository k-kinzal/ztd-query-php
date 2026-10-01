<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
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
     * @param CallableGraph $caller Lexical class context
     * @param State $state Called class context
     * @param Term $value Tested value
     * @param Term $bound Target class name
     * @param bool $literal Whether the tested class uses literal syntax
     * @return Term Known relation or a symbolic instance predicate
     */
    public function instance(CallableGraph $caller, State $state, Term $value, Term $bound, bool $literal = true): Term
    {
        if (in_array($value->kind, ['constant', 'array'], true)) {
            return Term::constant(false);
        }
        $names = new ClassNames($this->context);
        $class = $names->object($value);
        $target = $names->object($bound) ?? ($bound->kind === 'constant' && is_string($bound->literal) ? $bound->literal : null);
        if ($class !== null && $target === null && in_array($bound->kind, ['constant', 'array', 'uninitialized'], true)) {
            return new Term('throwable', 'Error');
        }
        if ($class !== null && $target !== null) {
            $dispatch = new Dispatch($this->context->program);
            $target = $literal ? $dispatch->className($target, $caller->className, $state->lateStaticClass) : ltrim($target, '\\');
            return $literal && $target === '' ? new Term('throwable', 'Error') : Term::constant($dispatch->subtype($class, $target));
        }
        return new Term('instanceof', operands: [$value, $bound], attributes: ['type' => 'bool']);
    }
}
