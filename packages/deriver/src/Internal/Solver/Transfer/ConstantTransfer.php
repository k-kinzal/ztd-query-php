<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Transfer;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\Dispatch;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Evaluates captured constant expressions without loading application PHP.
 * @visibility root
 */
final class ConstantTransfer
{
    /**
     * @param Machine $machine Shared abstract evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }
    /**
     * Resolves a constant initializer or the ordinary built-in constant rules.
     * @param CallableIR $caller Lexical scope
     * @param Instruction $instruction Constant fetch
     * @param State $state Current path
     * @return list<State> Constant value and possible initializer exceptions
     */
    public function apply(CallableIR $caller, Instruction $instruction, State $state): array
    {
        if ($instruction->operation === 'class-constant') {
            return $this->member($caller, $instruction, $state);
        }
        return $this->initializer($instruction->name, $instruction, $state);
    }

    /**
     * Resolves constant inheritance, singleton enum identity, and lexical access.
     * @param CallableIR $caller Calling scope
     * @param Instruction $instruction Class constant fetch
     * @param State $state Current state
     * @return list<State> Values or target access errors
     */
    public function member(CallableIR $caller, Instruction $instruction, State $state): array
    {
        $class = $state->value($instruction->operands[0]);
        $name = $state->value($instruction->operands[1]);
        if ($class->kind !== 'constant' || $name->kind !== 'constant' || !is_string($class->literal) || !is_string($name->literal)) {
            $state->registers[$instruction->result] = $this->machine->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, 'dynamic-class-constant', [$class, $name]);
            return [$state];
        }
        $resolved = (new Dispatch($this->machine->context->program))->className($class->literal, $caller->className, $state->lateStaticClass);
        if (strtolower($name->literal) === 'class') {
            $state->registers[$instruction->result] = Term::constant($resolved, $class->isSecret());
            return [$state];
        }
        $lookup = new \Deriver\Internal\Solver\Call\Member\Constants($this->machine->context->program);
        $constant = $lookup->find($resolved, $name->literal);
        if ($constant === null && !isset($this->machine->context->program->classes()[strtolower($resolved)])) {
            $state->registers[$instruction->result] = $this->machine->context->frontier('INCOMPLETE_SOURCE', $instruction->source, $resolved . '::' . $name->literal);
            return [$state];
        }
        if ($constant === null || !$lookup->allowed($constant, $caller->className)) {
            $state->completion = new \Deriver\Internal\Solver\Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        return $this->initializer($constant->className . '::' . $constant->name, $instruction, $state, $constant);
    }

    /**
     * Executes a captured initializer in its declaring scope, preserving target type checks.
     * @param string $symbol Resolved declaration name
     * @param Instruction $instruction Originating fetch
     * @param State $state Current state
     * @param \Deriver\Internal\IR\ClassConstant|null $constant Optional class or enum contract
     * @return list<State> Initialized values and failures
     */
    public function initializer(string $symbol, Instruction $instruction, State $state, ?\Deriver\Internal\IR\ClassConstant $constant = null): array
    {
        $body = $this->machine->context->program->constant($symbol);
        if ($body === null) {
            $state->registers[$instruction->result] = $constant === null ? (new PureStep($this->machine->context))->constant($symbol, $instruction) : ($this->machine->context->program->classes()[strtolower($constant->className)]->constants[$constant->name] ?? Term::opaque('INCOMPLETE_SOURCE'));
            return [$state];
        }
        $result = [];
        foreach ($this->machine->run($body, new State(clone $state->memory)) as $exit) {
            $path = $state->fork();
            $path->memory = $exit->memory;
            $value = $exit->completion->value ?? Term::constant(null);
            if ($exit->completion->kind === 'throw') {
                $path->completion = $exit->completion;
                $result[] = $path;
                continue;
            }
            $check = (new \Deriver\Internal\Solver\Call\TypeBinding($this->machine->context))->check($value, $constant->type ?? 'mixed', true);
            if ($check->mustFail || $check->mayFail) {
                $failure = $path->fork();
                $failure->completion = new \Deriver\Internal\Solver\Completion('throw', new Term('throwable', 'TypeError'));
                $result[] = $failure;
                if ($check->mustFail) {
                    continue;
                }
            }
            $path->registers[$instruction->result] = $constant?->enum === true ? new Term('enum', $constant->className . '::' . $constant->name, ['name' => Term::constant($constant->name), 'value' => $check->value], ['class' => $constant->className]) : $check->value;
            $result[] = $path;
        }
        return $result;
    }
}
