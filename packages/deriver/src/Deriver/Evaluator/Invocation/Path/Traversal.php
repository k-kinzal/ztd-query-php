<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation\Path;

use Closure;
use Deriver\Deriver\Evaluator\Demand;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Request\CallPath;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;

/**
 * Matches caller ancestry backwards and opens only the selected invocation dependencies.
 * @visibility root
 */
final class Traversal
{
    private readonly Callers $callers;

    /**
     * Keeps pattern matching and discovered caller sites local to one query.
     */
    public function __construct(private readonly Evaluator $engine, private readonly CallPath $path)
    {
        $this->callers = new Callers($engine->context->index);
    }

    /**
     * Places the selected demand in every invocation matching the path.
     * @param Closure(Frame): Term $observe Selected observation in its bound frame
     */
    public function select(BodyIndex $graph, Closure $observe): Term
    {
        $last = count($this->path->callers) - 1;
        $positions = $this->path->atCall ? $this->path->advance($graph->body->symbol, $last) : [$last];
        return $this->join(array_map(fn (int $position): Closure => fn (): Term => $this->walk($graph, $position, $observe, $this->engine->context->budget->maxDepth), $positions));
    }

    /**
     * Keeps each matched chain's arguments together, including across unnamed ancestors.
     * @param Closure(Frame): Term $next Downstream demand
     * @param array<string, true> $seen Matching states on this dependency branch
     */
    public function walk(BodyIndex $graph, int $position, Closure $next, int $depth, array $seen = []): Term
    {
        if ($position < 0) {
            return $this->join(array_map(fn (Frame $frame): Closure => fn (): Term => $next($frame), (new Demand($this->engine))->frames($graph, $this->engine->context->entries)));
        }
        $context = $this->engine->context;
        $key = $graph->body->symbol . ':' . $position;
        if (isset($seen[$key]) && !(new Reachability($this->callers, $this->path, $context))->allows($graph, $position)) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Choice);
        }
        $reason = $context->boundary($depth) ?? $context->work() ?? (isset($seen[$key]) ? 'CYCLE' : null);
        if ($reason !== null) {
            return $this->residual($graph, $next, $reason);
        }
        $seen[$key] = true;
        $step = $this->path->callers[$position];
        $branches = [];
        foreach ($this->callers->sites($graph->body->symbol, $step === '...' ? null : $step) as [$caller, $call]) {
            foreach ($this->path->advance($caller->body->symbol, $position) as $remaining) {
                $branches[] = fn (): Term => $this->walk($caller, $remaining, fn (Frame $parent): Term => (new Edge($this->engine))->follow($parent, $call, $graph, $depth, $next), $depth - 1, $seen);
            }
        }
        return $this->join($branches);
    }

    /**
     * Keeps an unfinished ancestry condition attached to the selected downstream value.
     * @param Closure(Frame): Term $next Downstream demand
     */
    public function residual(BodyIndex $graph, Closure $next, string $reason): Term
    {
        $frame = new Frame($graph, 'path-boundary:' . $graph->body->symbol);
        $reference = $this->engine->context->reference($frame, 'call-path', $graph->body->source, reason: $reason);
        return (new Choices($this->engine->context->provenance))->map($next($frame), fn (Term $value): Term => $this->engine->context->provenance->operation(new Term(\Deriver\Deriver\Evaluator\TermKind::ControlledValue, operands: [$value, $reference], attributes: ['reason' => $reason]), [$value, $reference], 'call-path'));
    }

    /**
     * Shares the ordinary fair scheduler and keeps each resulting candidate's evidence.
     * @param list<Closure(): Term> $branches Matching alternatives
     */
    public function join(array $branches): Term
    {
        return (new Choices($this->engine->context->provenance))->make(array_map(static fn (Term $value): array => [$value, []], $this->engine->context->search->all($branches)));
    }
}
