<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\ClassConstant;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Member\Constants;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Constant\ClassNames;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
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
     * @param CallableGraph $caller Lexical scope
     * @param Instruction $instruction Constant fetch
     * @param State $state Current path
     * @return list<State> Constant value and possible initializer exceptions
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        if ($instruction->operation === 'class-constant') {
            return $this->member($caller, $instruction, $state);
        }
        return $this->initializer($instruction->name, $instruction, $state);
    }

    /**
     * Resolves constant inheritance, singleton enum identity, and lexical access.
     * @param CallableGraph $caller Calling scope
     * @param Instruction $instruction Class constant fetch
     * @param State $state Current state
     * @return list<State> Values or target access errors
     */
    public function member(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $class = $state->value($instruction->operands[0]);
        $name = $state->value($instruction->operands[1]);
        $names = new ClassNames($this->machine->context);
        $literal = ($instruction->attributes['literal-class'] ?? true) === true;
        $syntax = ($instruction->attributes['class-name'] ?? false) === true;
        $resolved = $syntax && !$literal ? $names->runtime($class) : $names->resolve($class, $literal, $caller, $state);
        if ($resolved->kind === 'throwable') {
            return $this->finish($instruction, $state, $resolved);
        }
        if ($resolved->kind !== 'constant' || !is_string($resolved->literal) || $name->kind !== 'constant' || !is_string($name->literal)) {
            return $this->finish($instruction, $state, Term::opaque('UNSUPPORTED_LANGUAGE_FEATURE', dependencies:[$class,$name]));
        }
        if (strtolower($name->literal) === 'class') {
            $canonical = $syntax ? $resolved->literal : $names->canonical($resolved->literal);
            return $this->finish($instruction, $state, $canonical === null ? Term::opaque('INCOMPLETE_SOURCE', dependencies:[$class,$name]) : Term::constant($canonical, $class->isSecret() || $name->isSecret()));
        }
        $resolved = $resolved->literal;
        $lookup = new Constants($this->machine->context->program);
        $constant = $lookup->find($resolved, $name->literal);
        if ($constant === null && $names->canonical($resolved) === null) {
            $state->registers[$instruction->result] = $this->machine->context->frontier('INCOMPLETE_SOURCE', $instruction->source, $resolved . '::' . $name->literal);
            return [$state];
        }
        if ($constant === null || !$lookup->allowed($constant, $caller->className)) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        return $this->initializer($constant->className . '::' . $constant->name, $instruction, $state, $constant);
    }

    /**
     * Applies an exact class value, conversion error, or explicit unresolved dependency.
     * @param Instruction $instruction Fetch destination and provenance
     * @param State $state Current path
     * @param Term $value Resolved outcome
     * @return list<State> One completed fetch path
     */
    public function finish(Instruction $instruction, State $state, Term $value): array
    {
        if ($value->kind === 'throwable') {
            $state->completion = new Completion('throw', $value);
        } else {
            $state->registers[$instruction->result] = $value->kind === 'opaque' && is_string($value->literal) ? $this->machine->context->frontier($value->literal, $instruction->source, 'dynamic-class-constant', array_values($value->operands)) : $value;
        }
        return [$state];
    }

    /**
     * Executes a captured initializer in its declaring scope, preserving target type checks.
     * @param string $symbol Resolved declaration name
     * @param Instruction $instruction Originating fetch
     * @param State $state Current state
     * @param ClassConstant|null $constant Optional class or enum contract
     * @return list<State> Initialized values and failures
     */
    public function initializer(string $symbol, Instruction $instruction, State $state, ?ClassConstant $constant = null): array
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
            $check = (new TypeBinding($this->machine->context))->check($value, $constant->type ?? 'mixed', true);
            if ($check->mustFail || $check->mayFail) {
                $failure = $path->fork();
                $failure->completion = new Completion('throw', new Term('throwable', 'TypeError'));
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
