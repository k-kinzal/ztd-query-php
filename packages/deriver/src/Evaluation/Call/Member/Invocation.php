<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Member;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\Creation\Builtins;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Native\Invocation as NativeInvocation;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Applies visibility, instance binding, and magic dispatch before invoking a method body.
 * @visibility root
 */
final class Invocation
{
    /**
     * @param Machine $machine Shared source evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Invokes one justified runtime target while retaining PHP access errors.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Call site
     * @param State $state Calling state
     * @param list<PassedArgument> $arguments Evaluated actual arguments
     * @param Term $receiver Runtime object or spelled class
     * @param string $class Resolved runtime class
     * @param string $name Requested method name
     * @param string|null $target Selected source symbol
     * @return list<State> Normal and exceptional completions
     */
    public function call(CallableGraph $caller, Instruction $instruction, State $state, array $arguments, Term $receiver, string $class, string $name, ?string $target): array
    {
        $context = $this->machine->context;
        $static = $instruction->operation === 'invoke-static';
        $method = $target === null ? null : $context->program->callable($target);
        $modeled = isset($context->models->models[strtolower($class . '::' . $name)]);
        if ($target === null && !$modeled) {
            $bound = (new MethodInvocation($this->machine))->receiver($receiver, $state, $class, $static);
            $native = (new NativeInvocation($this->machine))->apply($class, $name, $arguments, $state, $instruction, $bound, $caller->strict);
            if ($native !== null) {
                return $native;
            }
        }
        if (($method !== null && !(new Access($context->program))->allows($method, $caller->className)) || ($target === null && !$modeled && (isset($context->program->classes()[strtolower($class)]) || (new Builtins())->name($class) !== null))) {
            return $this->magic($caller, $instruction, $state, $arguments, $receiver, $class, $name);
        }
        $bound = (new MethodInvocation($this->machine))->receiver($receiver, $state, $class, $static);
        if ($method?->abstract === true || ($static && $method !== null && !$method->static && !$this->instance($bound, $class))) {
            return $this->error($state);
        }
        if ($method?->static === true) {
            $bound = null;
        }
        $calledClass = ($instruction->attributes['bound-callable'] ?? false) === true ? $state->lateStaticClass : (new MethodInvocation($this->machine))->calledClass($receiver, $state, $class, $static);
        return (new CallExecutor($this->machine))->symbol($target ?? $class . '::' . $name, $arguments, $state, $instruction, $bound, strict: $caller->strict, calledClass: $calledClass);
    }

    /**
     * Checks that an inherited instance method receives a compatible existing object.
     * @param Term|null $receiver Bound object, if any
     * @param string $class Explicitly requested runtime class
     * @return bool Whether instance invocation is justified
     */
    public function instance(?Term $receiver, string $class): bool
    {
        $actual = $receiver->attributes['class'] ?? $receiver->attributes['type'] ?? '';
        return $receiver !== null && is_string($actual) && (new Dispatch($this->machine->context->program))->subtype($actual, $class);
    }

    /**
     * Routes unavailable or inaccessible source methods through __call or __callStatic.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Call site
     * @param State $state Calling state
     * @param list<PassedArgument> $arguments Original evaluated actuals
     * @param Term $receiver Runtime object or class
     * @param string $class Resolved class
     * @param string $name Original method spelling
     * @return list<State> Magic invocation or PHP access error
     */
    public function magic(CallableGraph $caller, Instruction $instruction, State $state, array $arguments, Term $receiver, string $class, string $name): array
    {
        $static = $instruction->operation === 'invoke-static';
        $symbol = (new Dispatch($this->machine->context->program))->method($class, $static ? '__callStatic' : '__call');
        if ($symbol === null) {
            if (!(new Dispatch($this->machine->context->program))->complete($class)) {
                $bound = (new MethodInvocation($this->machine))->receiver($receiver, $state, $class, $static);
                return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, $bound, 'OPEN_DISPATCH');
            }
            return $this->error($state);
        }
        foreach ($arguments as $argument) {
            if ($argument->name === '*') {
                return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, $receiver, 'UNSUPPORTED_LANGUAGE_FEATURE');
            }
        }
        $signature = new CallableGraph('magic-arguments', [new Parameter('arguments', variadic: true)], [], $instruction->source);
        $ordered = (new ArgumentOrder())->normalize($signature, $arguments);
        if (is_string($ordered)) {
            return $this->error($state);
        }
        $actuals = [new PassedArgument(Term::constant($name)), $ordered['arguments']];
        $calledClass = ($instruction->attributes['bound-callable'] ?? false) === true ? $state->lateStaticClass : (new MethodInvocation($this->machine))->calledClass($receiver, $state, $class, $static);
        return (new CallExecutor($this->machine))->symbol($symbol, $actuals, $state, $instruction, $static ? null : $receiver, strict: $caller->strict, calledClass: $calledClass);
    }

    /**
     * Produces a PHP method or allocation access error without applying the body effects.
     * @param State $state State after argument evaluation
     * @return list<State> Exceptional completion
     */
    public function error(State $state): array
    {
        $state->completion = new Completion('throw', new Term('throwable', 'Error'));
        return [$state];
    }
}
