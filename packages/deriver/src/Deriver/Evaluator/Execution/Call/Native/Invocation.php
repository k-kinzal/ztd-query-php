<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Native;

use Deriver\Deriver\Evaluator\Domain\Lattice;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation as MemberInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Applies native throwable constructors and getters using source-compatible heap state.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Invocation
{
    /**
     * @param Evaluator $machine Shared evaluator and type binding
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Executes a supported inherited native method or retains an explicit native boundary.
     * @param string $class Selected runtime or parent class
     * @param string $method Requested method spelling
     * @param list<PassedArgument> $arguments Evaluated actuals
     * @param State $state Calling path
     * @param Instruction $instruction Call or allocation site
     * @param Term|null $receiver Existing object identity
     * @param bool $strict Caller scalar coercion mode
     * @return list<State>|null Native alternatives, or no known native method
     */
    public function apply(string $class, string $method, array $arguments, State $state, Instruction $instruction, ?Term $receiver, bool $strict): ?array
    {
        $signatures = new Signatures($this->machine->context->program);
        $family = $signatures->family($class);
        if ($family === '') {
            return null;
        }
        $method = strtolower($method);
        $slot = (new Properties($this->machine->context->program))->getter($family, $method);
        if ($method === '__clone' || !(new MemberInvocation($this->machine))->instance($receiver, $class)) {
            return (new MemberInvocation($this->machine))->error($state);
        }
        if ($method !== '__construct' && $slot === null) {
            return in_array($method, ['getfile', 'getline', 'gettrace', 'gettraceasstring', '__tostring', '__wakeup'], true) ? (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, $receiver, 'MISSING_CALL_MODEL') : null;
        }
        foreach ($arguments as $argument) {
            if ($argument->name === '*') {
                return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, $receiver, 'UNSUPPORTED_LANGUAGE_FEATURE');
            }
        }
        $signature = $signatures->graph($family, $method, $instruction->source);
        $normalized = (new ArgumentOrder())->normalize($signature, $arguments);
        $actuals = $this->coercions($signature, $arguments, $strict);
        $result = [];
        foreach ((new ArgumentBinding($this->machine))->bind($signature, $state, $actuals, $receiver, strict: $strict) as $entry) {
            $next = $state->fork();
            $next->memory = $entry->memory;
            $next->completion = $entry->completion;
            if ($entry->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                if ($method === '__construct') {
                    $this->initialize($family, is_array($normalized) ? array_keys($normalized) : [], $entry, $next, $receiver);
                }
                $value = $slot === null ? Term::constant(null) : $next->memory->read(new Location('object:' . $receiver?->literal, [$slot]));
                $next->registers[$instruction->result] = $slot === 'message' ? (new Operations($this->machine->context->configuration->target->floatPrecision))->cast('string', $value) : $value;
            }
            $result[] = $next;
        }
        return $result;
    }

    /**
     * Preserves PHP 8.3 weak internal scalar null coercion without affecting source calls.
     * @param CallableGraph $signature Native signature
     * @param list<PassedArgument> $arguments Original actuals
     * @param bool $strict Calling-file coercion mode
     * @return list<PassedArgument> Native actuals after nullable scalar conversion
     */
    public function coercions(CallableGraph $signature, array $arguments, bool $strict): array
    {
        if ($strict) {
            return $arguments;
        }
        $types = [];
        foreach ($signature->parameters as $parameter) {
            $types[$parameter->name] = $parameter->type;
        }
        $result = [];
        foreach ($arguments as $position => $argument) {
            $type = $types[$argument->name ?? ($signature->parameters[$position]->name ?? '')] ?? '';
            if ($argument->value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $argument->value->literal === null && in_array($type, ['int', 'string'], true)) {
                $argument = new PassedArgument(Term::constant($type === 'int' ? 0 : ''), $argument->name, $argument->location);
            }
            $result[] = $argument;
        }
        return $result;
    }

    /**
     * Applies constructor fields, including skipped named defaults and retained previous links.
     * @param string $family Native constructor family
     * @param list<string> $provided Names present before omitted defaults were inserted
     * @param State $entry Bound native arguments
     * @param State $state Caller state receiving the heap writes
     * @param Term|null $receiver Validated object identity
     * @return void
     */
    public function initialize(string $family, array $provided, State $entry, State $state, ?Term $receiver): void
    {
        $parameters = array_keys((new Signatures($this->machine->context->program))->parameters($family));
        $last = -1;
        foreach ($parameters as $index => $name) {
            if (in_array($name, $provided, true)) {
                $last = $index;
            }
        }
        $base = $family === 'Error' ? 'Error' : 'Exception';
        foreach ($parameters as $index => $name) {
            $value = $entry->memory->read($entry->local($name));
            if ($index > $last && !in_array($name, ['severity', 'line'], true)) {
                continue;
            }
            $value = $this->assigned($name, $value, $entry);
            if ($value === null) {
                continue;
            }
            $slot = match ($name) {
                'filename' => 'file', 'previous' => $base . '::previous', default => $name
            };
            $address = new Location('object:' . $receiver?->literal, [$slot]);
            if (($name === 'code' && $value->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) || ($name === 'previous' && !in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Object], true))) {
                $value = (new Lattice())->widen($value, $state->memory->read($address));
            }
            $state->memory->write($address, $value);
        }
    }
    /**
     * Resolves fields whose native constructor deliberately retains an existing value.
     * @param string $name Constructor parameter
     * @param Term $value Bound argument
     * @param State $entry Bound arguments including filename
     * @return Term|null Assigned value or null when the existing slot is retained
     */
    public function assigned(string $name, Term $value, State $entry): ?Term
    {
        if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return $value;
        }
        if ($name === 'code' && $value->literal === 0) {
            return null;
        }
        if ($value->literal !== null || !in_array($name, ['previous', 'filename', 'line'], true)) {
            return $value;
        }
        if ($name !== 'line') {
            return null;
        }
        $filename = $entry->memory->read($entry->local('filename'));
        if ($filename->kind === \Deriver\Deriver\Evaluator\TermKind::Constant) {
            return $filename->literal === null ? null : Term::constant(0);
        }
        return Term::opaque('RUNTIME_STACK', 'int');
    }
}
