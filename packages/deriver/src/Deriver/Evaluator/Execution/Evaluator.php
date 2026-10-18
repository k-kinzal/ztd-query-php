<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution;

use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Control\LoopConvergence;
use Deriver\Deriver\Evaluator\Execution\Control\ResidualPaths;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Terminator;

/**
 * Executes demanded control-flow graphs over correlated abstract states.
 * @visibility namespace
 * @visibility Deriver\Deriver\Execution
 */
final class Evaluator
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
        if ($this->recursive($key, $initial)) {
            $completed = (new ResidualPaths($this->context))->invocation($initial, $callable->source, 'recursive-specialization');
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
     * Checks the recursion budgets; recursion over symbolic inputs forks at every level, so it is bounded more tightly.
     * @param string $key Callable identity
     * @param State $initial Bound entry state
     * @return bool Whether this activation exceeds a recursion budget
     */
    public function recursive(string $key, State $initial): bool
    {
        $active = $this->context->active[$key];
        $budget = $this->context->query->budget();
        if ($active > $budget->recursion) {
            return true;
        }
        if ($active <= $budget->symbolicRecursion) {
            return false;
        }
        foreach ($initial->locals as $name => $location) {
            $value = $initial->memory->materialize($initial->memory->read($location));
            if ($name !== 'this' && !$value->isConcrete()) {
                return true;
            }
        }
        return false;
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
            $successors = $state->completion->kind === Completion\CompletionKind::Normal ? $this->block($callable, $state) : [$state];
            foreach ($successors as $next) {
                if ($next->completion->kind === Completion\CompletionKind::Normal) {
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
        if ($state->completion->kind !== Completion\CompletionKind::Return) {
            return [$state];
        }
        $value = $state->completion->value ?? \Deriver\Deriver\Evaluator\Term::constant(null);
        $check = (new TypeBinding($this->context))->check($state->memory->dereference($value), (new TypeBinding($this->context))->declared($callable->returnType, $callable, $state), $callable->strict);
        (new TypeBinding($this->context))->report($check, $callable->source, $state);
        $exception = $state->fork();
        $exception->completion = new Completion\Completion(Completion\CompletionKind::Throw, $check->exception());
        if ($check->mustFail || $callable->returnType === 'never') {
            return [$exception];
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Cell && is_string($value->literal)) {
            $state->memory->write(new Location($value->literal), $check->value);
        } else {
            $state->completion = new Completion\Completion(Completion\CompletionKind::Return, $check->value);
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
        if ($state->stableHeader === $state->block && $block->terminator->kind !== \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Branch) {
            return [];
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
                    if ($result->completion->kind === Completion\CompletionKind::Normal) {
                        $next[] = $result;
                    } else {
                        $routed = $result->completion->kind === Completion\CompletionKind::Observed ? [$result] : (new Unwinding($this->context->program))->routes($result);
                        array_push($completed, ...$routed);
                    }
                }
            }
            $paths = [];
            foreach ((new StateJoin($this->context))->limit($next, $callable) as $bounded) {
                if ($bounded->completion->kind === Completion\CompletionKind::Normal) {
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
        $collector->instruction($callable, $instruction, $state, \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Before);
        $collector->instruction($callable, $instruction, $state, \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Invocation);
        if ($state->completion->kind === Completion\CompletionKind::Observed) {
            return [$state];
        }
        $before = $state->fork();
        $paths = (new InstructionTransfer($this))->apply($callable, $instruction, $state);
        $id = $instruction->id;
        (new Dependencies($this->context))->record($instruction, $before, $paths);
        foreach ($paths as $path) {
            if (!in_array($id, $path->evidence, true)) {
                $path->evidence[] = $id;
            }
            $value = $path->registers[$instruction->result] ?? null;
            if ($value?->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                $path->completion = new Completion\Completion(Completion\CompletionKind::Throw, $value);
            }
            if ($path->completion->kind === Completion\CompletionKind::Normal) {
                $collector->instruction($callable, $instruction, $path, \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After);
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
        if ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Exit) {
            $state->completion = new Completion\Completion(Completion\CompletionKind::Exit);
            return [$state];
        }
        if ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Branch) {
            return $this->branch($end, $state);
        }
        if ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Residual) {
            return (new ResidualPaths($this->context))->complete($state, $state->value($end->operand));
        }
        if ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Jump) {
            $state->previous = $state->block;
            $state->block = $end->targets[0];
            return [$state];
        }
        if ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::LeaveTry) {
            $handler = end($state->handlers);
            $state->completion = new Completion\Completion(Completion\CompletionKind::Jump, target: $handler === false ? 0 : $handler->region->continuation, depth: max(0, count($state->handlers) - 1));
        } elseif ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Resume) {
            $handler = array_pop($state->handlers);
            $state->completion = $handler->saved ?? new Completion\Completion(Completion\CompletionKind::Return, \Deriver\Deriver\Evaluator\Term::constant(null));
        } elseif ($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::CompleteJump) {
            $state->completion = new Completion\Completion(Completion\CompletionKind::Jump, target: $end->targets[0], depth: $end->handlerDepth);
        } else {
            $value = $end->operand === '' ? \Deriver\Deriver\Evaluator\Term::constant(null) : ($callable->byReference ? ($state->registers[$end->operand] ?? \Deriver\Deriver\Evaluator\Term::opaque('UNCOMPUTED_REGISTER')) : $state->value($end->operand));
            $state->completion = new Completion\Completion($end->kind === \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Throw ? Completion\CompletionKind::Throw : Completion\CompletionKind::Return, $value);
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
