<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Creation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Member\Access as MemberAccess;
use Deriver\Evaluation\Call\Member\Invocation;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Validates class and lifecycle access before applying object initialization effects.
 * @visibility root
 */
final class Access
{
    /**
     * @param Machine $machine Shared evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Resolves a literal class or the class of a known runtime object.
     * @param Term $source Evaluated new/clone operand
     * @param CallableGraph $caller Lexical scope
     * @param State $state Late static context
     * @param bool $literal Whether the source uses a literal class reference
     * @return string|null Known canonical class
     */
    public function name(Term $source, CallableGraph $caller, State $state, bool $literal = true): ?string
    {
        $name = in_array($source->kind, ['object', 'enum'], true) ? ($source->attributes['class'] ?? null) : ($source->kind === 'constant' ? $source->literal : null);
        if (!is_string($name)) {
            return null;
        }
        $name = $literal ? (new Dispatch($this->machine->context->program))->className($name, $caller->className, $state->lateStaticClass) : ltrim($name, '\\');
        return $this->machine->context->program->classes()[strtolower($name)]->name ?? (new Builtins())->name($name) ?? $name;
    }

    /**
     * Preserves access errors and unresolved allocation effects before creating known state.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Allocation site
     * @param State $state State after argument evaluation
     * @param list<PassedArgument> $arguments Constructor actuals
     * @return list<State>|null Error/boundary paths or permission to initialize the object
     */
    public function check(CallableGraph $caller, Instruction $instruction, State $state, array $arguments): ?array
    {
        $context = $this->machine->context;
        $source = $state->value($instruction->operands[0]);
        $clone = $instruction->operation === 'clone';
        $class = $this->name($source, $caller, $state, ($instruction->attributes['literal-class'] ?? true) === true);
        $error = new Invocation($this->machine);
        if ($this->invalidOperand($source, $clone)) {
            return $error->error($state);
        }
        if ($class !== null && in_array(strtolower($class), ['', 'self', 'parent', 'static'], true)) {
            return $error->error($state);
        }
        if ($class === null) {
            return (new UnknownCall($context))->apply($state, $instruction, $arguments, $source, 'OPEN_DISPATCH');
        }
        $failure = $this->lifecycle($class, $caller, $state, $clone);
        if ($failure !== null) {
            return $failure;
        }
        $declaration = $context->program->classes()[strtolower($class)] ?? null;
        $target = (new Dispatch($context->program))->method($class, $clone ? '__clone' : '__construct');
        if ($declaration === null && (new Builtins())->name($class) === null && !isset($context->models->models[strtolower($class . '::__construct')])) {
            return (new UnknownCall($context))->apply($state, $instruction, $arguments, $source, 'INCOMPLETE_SOURCE');
        }
        if ($clone && (new Signatures($context->program))->family($class) !== '') {
            return $error->error($state);
        }
        return !$clone && $target === null && !isset($context->models->models[strtolower($class . '::__construct')]) && (new Signatures($context->program))->family($class) === '' ? $this->withoutConstructor($state, $instruction, $arguments, $source) : null;
    }

    /**
     * Checks instantiability and constructor or clone visibility independently of actuals.
     * @param string $class Canonical class
     * @param CallableGraph $caller Lexical caller
     * @param State $state State after evaluating arguments
     * @param bool $clone Whether cloning an existing instance
     * @return list<State>|null Access error or valid lifecycle entry
     */
    public function lifecycle(string $class, CallableGraph $caller, State $state, bool $clone): ?array
    {
        $context = $this->machine->context;
        $error = new Invocation($this->machine);
        $declaration = $context->program->classes()[strtolower($class)] ?? null;
        if (!$clone && (($declaration->abstract ?? false) || ($declaration->interface ?? false) || ($declaration->enum ?? false) || $class === 'Throwable')) {
            return $error->error($state);
        }
        $target = (new Dispatch($context->program))->method($class, $clone ? '__clone' : '__construct');
        $method = $target === null ? null : $context->program->callable($target);
        if ($method !== null && !(new MemberAccess($context->program))->allows($method, $caller->className)) {
            return $error->error($state);
        }
        return null;
    }

    /**
     * Rejects named arguments to a class with no constructor.
     * @param State $state State after argument evaluation
     * @param Instruction $instruction Allocation site
     * @param list<PassedArgument> $arguments Constructor actuals
     * @param Term $source Evaluated class operand
     * @return list<State>|null Error or unresolved unpack paths, otherwise valid initialization
     */
    public function withoutConstructor(State $state, Instruction $instruction, array $arguments, Term $source): ?array
    {
        foreach ($arguments as $argument) {
            if ($argument->name === '*') {
                return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, $source, 'UNSUPPORTED_LANGUAGE_FEATURE');
            }
            if ($argument->name !== null) {
                return (new Invocation($this->machine))->error($state);
            }
        }
        return null;
    }

    /**
     * Checks the distinct concrete operand categories accepted by new and clone.
     * @param Term $source Evaluated operand
     * @param bool $clone Whether the operation clones an existing object
     * @return bool Whether the operand necessarily causes a target Error
     */
    public function invalidOperand(Term $source, bool $clone): bool
    {
        return $clone ? in_array($source->kind, ['constant', 'array', 'enum'], true) : ($source->kind === 'array' || $source->kind === 'constant' && !is_string($source->literal));
    }
}
