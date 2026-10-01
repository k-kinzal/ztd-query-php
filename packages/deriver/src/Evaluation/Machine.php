<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\Control\ResidualPaths;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Executes demanded control-flow graphs over correlated abstract states.
 * @visibility root
 */
final class Machine
{
    /**
     * @param Context $context Query-local worklist and evidence
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Evaluates one callable with bounded loops and recursive specializations.
     * @param CallableGraph $callable Demanded graph
     * @param State $initial Bound entry state
     * @return list<State> Normal and exceptional completions
     */
    public function run(CallableGraph $callable, State $initial): array
    {
        $key = (new CallableIdentity())->key($callable->symbol);
        $this->context->active[$key] = ($this->context->active[$key] ?? 0) + 1;
        $available = $this->context->available($callable->source, call: true);
        if (!$available || $this->context->active[$key] > $this->context->query->budget()->recursion) {
            $completed = (new ResidualPaths($this->context))->seal($initial, $callable->source, $available ? 'recursive-specialization' : 'runtime-resources');
        } else {
            $this->context->graphs[$key] = true;
            $this->context->demands[$callable] ??= (new Discovery($this->context))->instructions($callable);
            $completed = (new Evaluation($this))->run($callable, $initial);
        }
        foreach ($completed as $exit) {
            foreach (array_keys($exit->iterators) as $iterator) {
                unset($exit->memory->liveArrays[$iterator]);
            }
            (new ObservationCollector($this->context))->completion($callable, $exit);
        }
        $this->context->active[$key]--;
        return $completed;
    }

    /**
     * Transfers a callable once using available interprocedural approximations.
     * @param CallableGraph $callable Demanded graph
     * @param State $initial Independently writable entry
     * @return list<State> Candidate correlated completions
     */
    public function execute(CallableGraph $callable, State $initial): array
    {
        $pending = [$initial];
        $completed = [];
        while ($pending !== []) {
            $state = array_shift($pending);
            $successors = $state->completion->kind === 'normal' ? $this->block($callable, $state) : [$state];
            foreach ($successors as $next) {
                if ($next->completion->kind === 'normal') {
                    $pending[] = $next;
                } else {
                    foreach ($this->returned($callable, $next) as $exit) {
                        $completed[] = $exit;
                    }
                }
            }
            $pending = (new StateJoin($this->context))->limit($pending, $callable);
            $completed = (new StateJoin($this->context))->completed($completed, $callable);
        }
        return $completed;
    }

    /**
     * Enforces declared return types after finally has selected the final completion.
     * @param CallableGraph $callable Completed callable
     * @param State $state Completed path
     * @return list<State> Valid return and possible type error
     */
    public function returned(CallableGraph $callable, State $state): array
    {
        if ($state->completion->kind !== 'return') {
            return [$state];
        }
        $value = $state->completion->value ?? Term::constant(null);
        $check = (new TypeBinding($this->context))->check($state->memory->dereference($value), (new TypeBinding($this->context))->declared($callable->returnType, $callable, $state), $callable->strict);
        (new TypeBinding($this->context))->report($check, $callable->source, $state);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $check->exception());
        if ($check->mustFail || $callable->returnType === 'never') {
            return [$exception];
        }
        if ($value->kind === 'cell' && is_string($value->literal)) {
            $state->memory->write(new Location($value->literal), $check->value);
        } else {
            $state->completion = new Completion('return', $check->value);
        }
        return $check->mayFail ? [$state, $exception] : [$state];
    }

    /**
     * Transfers a basic block and its exceptional edges.
     * @param CallableGraph $callable Current callable
     * @param State $state Block entry
     * @return list<State> Successors or completed paths
     */
    public function block(CallableGraph $callable, State $state): array
    {
        $block = $callable->blocks[$state->block];
        if ($block->loopHeader && (new LoopConvergence($this->context))->widen($callable, $state)) {
            return (new ResidualPaths($this->context))->seal($state, $callable->source, 'loop-fixed-point');
        }
        $paths = [$state];
        $completed = [];
        foreach ($block->instructions as $instruction) {
            if (!$this->context->sealed && !isset($this->context->demands[$callable][$instruction->id])) {
                continue;
            }
            $next = [];
            foreach ($paths as $path) {
                if (!$this->context->admit($instruction->source)) {
                    array_push($completed, ...(new ResidualPaths($this->context))->seal($path, $instruction->source, 'logical-work'));
                    continue;
                }
                foreach ($this->step($callable, $instruction, $path) as $result) {
                    if ($result->completion->kind === 'normal') {
                        $next[] = $result;
                    } else {
                        $routed = $result->completion->kind === 'observed' ? [$result] : (new Unwinding($this->context->program))->routes($result);
                        array_push($completed, ...$routed);
                    }
                }
            }
            $paths = [];
            foreach ((new StateJoin($this->context))->limit($next, $callable) as $bounded) {
                if ($bounded->completion->kind === 'normal') {
                    $paths[] = $bounded;
                } else {
                    $completed[] = $bounded;
                }
            }
            $completed = (new StateJoin($this->context))->completed($completed, $callable);
        }
        foreach ($paths as $path) {
            array_push($completed, ...$this->terminate($callable, $block->terminator, $path));
        }
        return $completed;
    }

    /**
     * Transfers an instruction and records its dependency evidence.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction Current instruction
     * @param State $state Input path
     * @return list<State> Resulting paths
     */
    public function step(CallableGraph $callable, Instruction $instruction, State $state): array
    {
        $collector = new ObservationCollector($this->context);
        $collector->instruction($callable, $instruction, $state, 'before');
        $collector->instruction($callable, $instruction, $state, 'invocation');
        if ($state->completion->kind === 'observed') {
            return [$state];
        }
        $before = $state->fork();
        $paths = (new InstructionTransfer($this))->apply($callable, $instruction, $state);
        $id = $instruction->id;
        (new Dependencies($this->context))->record($instruction, $before, $paths);
        foreach ($paths as $path) {
            $path->evidence[] = $id;
            $value = $path->registers[$instruction->result] ?? null;
            if ($value?->kind === 'throwable') {
                $path->completion = new Completion('throw', $value);
            }
            if ($path->completion->kind === 'normal') {
                $collector->instruction($callable, $instruction, $path, 'after');
            }
        }
        return $paths;
    }

    /**
     * Routes explicit branch, return, and resumable completion terminators.
     * @param CallableGraph $callable Current callable
     * @param Terminator $end Block terminator
     * @param State $state Block exit
     * @return list<State> Next paths
     */
    public function terminate(CallableGraph $callable, Terminator $end, State $state): array
    {
        if ($end->kind === 'exit') {
            $state->completion = new Completion('exit');
            return [$state];
        }
        if ($end->kind === 'branch') {
            return $this->branch($end, $state);
        }
        if ($end->kind === 'jump') {
            $state->previous = $state->block;
            $state->block = $end->targets[0];
            return [$state];
        }
        if ($end->kind === 'leave-try') {
            $handler = end($state->handlers);
            $state->completion = new Completion('jump', target: $handler === false ? 0 : $handler->region->continuation, depth: max(0, count($state->handlers) - 1));
        } elseif ($end->kind === 'resume') {
            $handler = array_pop($state->handlers);
            $state->completion = $handler->saved ?? new Completion('return', Term::constant(null));
        } elseif ($end->kind === 'complete-jump') {
            $state->completion = new Completion('jump', target: $end->targets[0], depth: $end->handlerDepth);
        } else {
            $value = $end->operand === '' ? Term::constant(null) : ($callable->byReference ? ($state->registers[$end->operand] ?? Term::opaque('UNCOMPUTED_REGISTER')) : $state->value($end->operand));
            $state->completion = new Completion($end->kind === 'throw' ? 'throw' : 'return', $value);
        }
        return (new Unwinding($this->context->program))->routes($state);
    }

    /**
     * Forks a correlated tuple of values and memory under one predicate.
     * @param Terminator $end Branch
     * @param State $state Input path
     * @return list<State> Feasible branches
     */
    public function branch(Terminator $end, State $state): array
    {
        $result = [];
        foreach ([true, false] as $index => $truth) {
            if ($truth && $state->stableHeader === $state->block) {
                continue;
            }
            $next = $state->fork();
            if ((new Constraints($this->context))->assume($next, $state->value($end->operand), $truth)) {
                $next->stableHeader = null;
                if (isset($state->producers[$end->operand])) {
                    $next->controls[] = $state->producers[$end->operand];
                    $next->controls = array_values(array_unique($next->controls));
                }
                $next->previous = $state->block;
                $next->block = $end->targets[$index];
                $result[] = $next;
            }
        }
        return $result;
    }
}
