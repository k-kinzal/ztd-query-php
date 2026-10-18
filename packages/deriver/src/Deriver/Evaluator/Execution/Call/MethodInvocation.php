<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Access;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Resolves known receivers and retains open-world alternatives for declared types.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class MethodInvocation
{
    /**
     * @param Evaluator $machine Shared source/evaluator evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Evaluates a method call for every justified target.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Method call site
     * @param State $state Calling path
     * @param list<PassedArgument> $arguments Evaluated actuals
     * @return list<State> Target alternatives with correlated effects
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, array $arguments): array
    {
        $context = $this->machine->context;
        $receiver = $state->value($instruction->operands[0]);
        $name = $state->value($instruction->operands[1]);
        if ($name->kind !== \Deriver\Deriver\Evaluator\TermKind::Constant || !is_string($name->literal)) {
            return (new UnknownCall($context))->apply($state, $instruction, $arguments, $receiver, 'OPEN_DISPATCH');
        }
        $static = $instruction->operation === 'invoke-static';
        $resolved = (new ClassNames($context))->receiver($caller, $instruction, $state, $receiver, $static);
        if ($resolved->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
            return (new Invocation($this->machine))->error($state);
        }
        $class = $resolved->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && is_string($resolved->literal) ? $resolved->literal : '';
        if (!$static && in_array($receiver->kind, [\Deriver\Deriver\Evaluator\TermKind::Constant, \Deriver\Deriver\Evaluator\TermKind::Array], true)) {
            return (new Invocation($this->machine))->error($state);
        }
        $knownTarget = (new Access($context->program))->target($class, $caller->className, $name->literal, $static);
        $provided = ($static || $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object) && $knownTarget !== null ? new DispatchDecision([]) : (new ProviderDispatch($context))->resolve(new DispatchRequest($receiver, $name->literal, $static, $instruction->source, $context->configuration->target));
        if (($static || $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Object) && ($knownTarget !== null || $provided->targets === [] && !$provided->exhaustive)) {
            return (new Invocation($this->machine))->call($caller, $instruction, $state, $arguments, $receiver, $class, $name->literal, $knownTarget);
        }
        return $this->candidates($caller, $instruction, $state, $arguments, $receiver, $class, $name->literal, $provided);
    }

    /**
     * Evaluates provider and source candidates with an explicit open-world remainder.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Call site
     * @param State $state Input state
     * @param list<PassedArgument> $arguments Evaluated actuals
     * @param Term $receiver Runtime receiver bound
     * @param string $class Resolved declared class
     * @param string $name Method spelling
     * @param DispatchDecision $provided Provider alternatives
     * @return list<State> Inclusive target alternatives
     */
    public function candidates(CallableGraph $caller, Instruction $instruction, State $state, array $arguments, Term $receiver, string $class, string $name, DispatchDecision $provided): array
    {
        $context = $this->machine->context;
        $dispatch = new Dispatch($context->program);
        $result = [];
        foreach ($provided->targets as $candidate) {
            $path = $state->fork();
            if ($candidate->condition !== null && !(new Constraints($context))->assume($path, $candidate->condition, true)) {
                continue;
            }
            array_push($result, ...(new CallExecutor($this->machine))->symbol($candidate->symbol, $arguments, $path, $instruction, $candidate->receiver ?? $receiver, strict: $caller->strict));
        }
        $candidates = $dispatch->candidates($class, $name);
        array_push($result, ...$this->handled($caller, $instruction, $state, $arguments, $receiver, $class, $name, $candidates));
        foreach ($candidates as $candidate => $target) {
            $path = $state->fork();
            if (!(new Constraints($context))->assume($path, new Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '!==', [$receiver, Term::constant(null)], ['type' => 'bool']), true)) {
                continue;
            }
            $object = new Term(\Deriver\Deriver\Evaluator\TermKind::Object, $this->identity($receiver, $path), attributes: ['class' => $candidate]);
            array_push($result, ...(new Invocation($this->machine))->call($caller, $instruction, $path, $arguments, $object, $candidate, $name, $dispatch->method($candidate, $name)));
        }
        $bound = implode('|', array_diff(explode('|', $class), ['null']));
        if ($bound !== $class) {
            $null = $state->fork();
            if ((new Constraints($context))->assume($null, new Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '!==', [$receiver, Term::constant(null)], ['type' => 'bool']), false)) {
                array_push($result, ...(new Invocation($this->machine))->error($null));
            }
        }
        $closed = $provided->exhaustive || ($context->program->classes()[strtolower($bound)]->final ?? false);
        if (!$closed || $result === []) {
            array_push($result, ...(new UnknownCall($context))->apply($state->fork(), $instruction, $arguments, $receiver, 'OPEN_DISPATCH'));
        }
        return $result;
    }

    /**
     * Keeps a symbolic receiver's identity only when its spelling denotes one object along the path.
     * @param Term $receiver Runtime receiver bound
     * @param State $path Dispatched path
     * @return string Stable input identity, or a fresh one for residuals that may denote distinct objects
     */
    public function identity(Term $receiver, State $path): string
    {
        $stable = in_array($receiver->kind, [\Deriver\Deriver\Evaluator\TermKind::Parameter, \Deriver\Deriver\Evaluator\TermKind::External, \Deriver\Deriver\Evaluator\TermKind::Object], true) || $receiver->kind === \Deriver\Deriver\Evaluator\TermKind::Opaque && isset($receiver->attributes['stability']);
        return $stable && is_string($receiver->literal) ? $receiver->literal : $path->memory->fresh('parameter-object');
    }

    /**
     * Invokes user evaluators declared on the receiver's type bound, such as framework classes without source.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Call site
     * @param State $state Input state
     * @param list<PassedArgument> $arguments Evaluated actuals
     * @param Term $receiver Runtime receiver bound
     * @param string $class Resolved declared class
     * @param string $name Method spelling
     * @param array<string, string> $candidates Source implementations already selected
     * @return list<State> Handled alternatives for a non-null receiver
     */
    public function handled(CallableGraph $caller, Instruction $instruction, State $state, array $arguments, Term $receiver, string $class, string $name, array $candidates): array
    {
        $context = $this->machine->context;
        $result = [];
        foreach (array_diff(explode('|', $class), ['null', '']) as $type) {
            if (isset($candidates[$type]) || !isset($context->evaluators->evaluators[strtolower($type . '::' . $name)])) {
                continue;
            }
            $path = $state->fork();
            if ((new Constraints($context))->assume($path, new Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '!==', [$receiver, Term::constant(null)], ['type' => 'bool']), true)) {
                array_push($result, ...(new Invocation($this->machine))->call($caller, $instruction, $path, $arguments, $receiver, $type, $name, null));
            }
        }
        return $result;
    }

    /**
     * Applies explicit late static binding while preserving forwarded self/parent calls.
     * @param Term $receiver Evaluated class or object
     * @param State $state Caller state
     * @param string $class Resolved target class
     * @param bool $static Static invocation syntax
     * @return Term|null Bound receiver for the selected method
     */
    public function receiver(Term $receiver, State $state, string $class, bool $static): ?Term
    {
        return $static ? (isset($state->locals['this']) ? $state->memory->read($state->locals['this']) : null) : $receiver;
    }

    /**
     * Selects the called class while forwarding self, parent, and static calls.
     * @param Term $receiver Spelled class or runtime object
     * @param State $state Calling context
     * @param string $class Resolved lookup class
     * @param bool $static Whether static syntax was used
     * @return string Late static binding for the callee only
     */
    public function calledClass(Term $receiver, State $state, string $class, bool $static): string
    {
        return $static && in_array(strtolower(is_string($receiver->literal) ? $receiver->literal : ''), ['self', 'parent', 'static'], true) ? $state->lateStaticClass : ((new ClassNames($this->machine->context))->canonical($class) ?? $class);
    }
}
