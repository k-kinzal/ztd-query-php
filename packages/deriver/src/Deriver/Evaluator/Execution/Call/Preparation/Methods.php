<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\MethodInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Access;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Checks method access and obtains argument modes before argument effects begin.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Methods
{
    /**
     * @param Evaluator $machine Shared declaration world
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Checks known method calls while allowing abstract signatures on unknown receivers.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Call origin
     * @param State $state Current receiver bindings
     * @param Term $receiver Evaluated receiver or class
     * @param Term $name Evaluated method name
     * @param bool $static Whether invocation uses static syntax
     * @return Target Signature or early access failure
     */
    public function resolve(CallableGraph $caller, Instruction $instruction, State $state, Term $receiver, Term $name, bool $static): Target
    {
        if (!$static && in_array($receiver->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Array], true)) {
            return new Target(error: 'Error');
        }
        if ($name->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_string($name->literal)) {
            return new Target();
        }
        $context = $this->machine->context;
        $resolved = (new ClassNames($context))->receiver($caller, $instruction, $state, $receiver, $static);
        if ($resolved->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
            return new Target(error: 'Error');
        }
        $class = $resolved->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($resolved->literal) ? $resolved->literal : '';
        $symbol = (new Access($context->program))->target($class, $caller->className, $name->literal, $static);
        $method = $symbol === null ? null : $context->program->callable($symbol);
        if ($method !== null && !(new Access($context->program))->allows($method, $caller->className)) {
            return $this->open($receiver, $class, $static) ? new Target() : $this->magic($instruction, $class, $static);
        }
        if ($method !== null) {
            return $this->declared($method, $instruction, $state, $receiver, $class, $static);
        }
        if (isset($context->evaluators->evaluators[strtolower($class . '::' . $name->literal)])) {
            return new Target((new CallResolution($context))->body($class . '::' . $name->literal, $instruction, receiverType: $class));
        }
        $native = $this->native($instruction, $state, $receiver, $class, $name->literal, $static);
        if ($native !== null) {
            return $native;
        }
        if ($this->open($receiver, $class, $static)) {
            return new Target();
        }
        return isset($context->program->classes()[strtolower($class)]) || (new Builtins())->name($class) !== null ? $this->magic($instruction, $class, $static) : new Target();
    }

    /**
     * Validates a known method's instance requirements before obtaining its replacement evaluator.
     * @param CallableGraph $method Accessible source declaration
     * @param Instruction $instruction \Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation origin
     * @param State $state Existing receiver bindings
     * @param Term $receiver Evaluated receiver or class
     * @param string $class Requested runtime class
     * @param bool $static Whether static syntax was used
     * @return Target Declared signature or an early instance error
     */
    public function declared(CallableGraph $method, Instruction $instruction, State $state, Term $receiver, string $class, bool $static): Target
    {
        $bound = (new MethodInvocation($this->machine))->receiver($receiver, $state, $class, $static);
        if (($static || $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object) && $method->abstract || $static && !$method->static && !(new Invocation($this->machine))->instance($bound, $class)) {
            return new Target(error: 'Error');
        }
        return new Target((new CallResolution($this->machine->context))->body($method->symbol, $instruction, receiverType: (new MethodInvocation($this->machine))->calledClass($receiver, $state, $class, $static)));
    }

    /**
     * Captures native throwable signatures without applying constructors or getters.
     * @param Instruction $instruction Origin
     * @param State $state Receiver bindings
     * @param Term $receiver Evaluated receiver
     * @param string $class Selected class
     * @param string $name Method name
     * @param bool $static Whether static syntax was used
     * @return Target|null Native signature, early error, or no native method
     */
    public function native(Instruction $instruction, State $state, Term $receiver, string $class, string $name, bool $static): ?Target
    {
        $signatures = new Signatures($this->machine->context->program);
        $family = $signatures->family($class);
        $name = strtolower($name);
        $getter = (new Properties($this->machine->context->program))->getter($family, $name);
        if ($family === '' || $getter === null && !in_array($name, ['__construct', '__clone', 'gettrace', 'gettraceasstring', '__tostring', '__wakeup'], true)) {
            return null;
        }
        $bound = (new MethodInvocation($this->machine))->receiver($receiver, $state, $class, $static);
        if ($name === '__clone' || !(new Invocation($this->machine))->instance($bound, $class)) {
            return new Target(error: 'Error');
        }
        return new Target($signatures->graph($family, $name, $instruction->source));
    }

    /**
     * Original magic-call actual arguments are passed by value in an argument array.
     * @param Instruction $instruction Original call
     * @param string $class Receiver class
     * @param bool $static Whether __callStatic applies
     * @return Target A value-argument signature or a missing-method access error
     */
    public function magic(Instruction $instruction, string $class, bool $static): Target
    {
        $method = (new Dispatch($this->machine->context->program))->method($class, $static ? '__callStatic' : '__call');
        if ($method === null) {
            return (new Dispatch($this->machine->context->program))->complete($class) ? new Target(error: 'Error') : new Target();
        }
        return new Target(new CallableGraph('magic-arguments', [], [], $instruction->source));
    }
    /**
     * Checks whether an unknown subtype can supply additional method behavior.
     * @param Term $receiver Evaluated receiver
     * @param string $class Declared class bound
     * @param bool $static Whether lookup uses a concrete class name
     * @return bool Whether subclass behavior remains open
     */
    public function open(Term $receiver, string $class, bool $static): bool
    {
        return !$static && $receiver->kind !== \Deriver\Deriver\Evaluator\TermKind::Object && !($this->machine->context->program->classes()[strtolower($class)]->final ?? false);
    }

}
