<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Closure;

use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Restores acquisition scope for first-class callables without changing the invoking frame.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Binding
{
    /**
     * @param Evaluator $machine Shared call evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Creates an access-check context with the invoking file's coercion policy.
     * @param Term $closure Captured callable
     * @param CallableGraph $caller Invoking source
     * @return CallableGraph Captured lexical scope
     */
    public function scope(Term $closure, CallableGraph $caller): CallableGraph
    {
        $scope = $closure->attributes['scope'] ?? '';
        return new CallableGraph($caller->symbol, [], [], $caller->source, strict: $caller->strict, className: is_string($scope) ? $scope : '');
    }

    /**
     * Binds a captured receiver and called class in temporary call state.
     * @param Term $closure First-class callable identity
     * @param State $caller Invoking state
     * @return State Captured call context
     */
    public function state(Term $closure, State $caller): State
    {
        $state = $caller->fork();
        $class = $closure->attributes['late-static'] ?? '';
        $state->lateStaticClass = is_string($class) ? $class : '';
        unset($state->locals['this']);
        if (isset($closure->operands['this'])) {
            $state->locals['this'] = $state->memory->allocate($closure->operands['this']);
        }
        return $state;
    }

    /**
     * Selects argument modes using access already granted at acquisition.
     * @param Term $closure Captured callable
     * @param CallableGraph $caller Invoking source
     * @param Instruction $instruction Invocation origin
     * @param State $state Invoking state
     * @return Target Captured signature
     */
    public function signature(Term $closure, CallableGraph $caller, Instruction $instruction, State $state): Target
    {
        return (new Resolution($this->machine))->function($this->scope($closure, $caller), $instruction, $this->state($closure, $state), $closure->operands['target']);
    }

    /**
     * Invokes under the captured scope, then restores the caller's frame bindings.
     * @param Term $closure Captured callable
     * @param list<PassedArgument> $arguments Actual arguments
     * @param State $state Invoking frame
     * @param Instruction $instruction Invocation origin
     * @param CallableGraph $caller Invoking source
     * @return list<State> Completed paths with caller locals preserved
     */
    public function invoke(Term $closure, array $arguments, State $state, Instruction $instruction, CallableGraph $caller): array
    {
        $paths = (new CallExecutor($this->machine))->invoke($closure->operands['target'], $arguments, $this->state($closure, $state), $instruction, $this->scope($closure, $caller));
        foreach ($paths as $path) {
            $path->locals = $state->locals;
            $path->lateStaticClass = $state->lateStaticClass;
        }
        return $paths;
    }
}
