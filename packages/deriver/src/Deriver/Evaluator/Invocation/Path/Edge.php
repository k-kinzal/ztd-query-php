<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation\Path;

use Closure;
use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Expression\Guards;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Invocation\Dispatch;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Binds one selected call as a unit, preserving correlated arguments and receiver context.
 * @visibility Deriver\Deriver\Evaluator
 */
final class Edge
{
    /**
     * Uses ordinary dispatch, lazy binding and configured branch evaluators.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**
     * Follows a call edge without deriving its caller's return or unrelated statements.
     * @param Closure(Frame): Term $next The selected downstream dependency
     */
    public function follow(Frame $caller, Instruction $call, BodyIndex $callee, int $depth, Closure $next): Term
    {
        $block = $caller->graph->positions[$call->result][0];
        return $this->observe($caller, $block, $depth, fn (): Term => $this->dispatch($caller, $call, $callee, $depth, $next));
    }

    /**
     * Checks the resolved declaration before binding the downstream dependency.
     * @param Closure(Frame): Term $next The selected downstream dependency
     */
    public function dispatch(Frame $caller, Instruction $call, BodyIndex $callee, int $depth, Closure $next): Term
    {
        return (new Dispatch($this->engine))->apply($caller, $call, $depth, function (string $target, ?Term $receiver, ?Term $callable) use ($caller, $call, $callee, $depth, $next): Term {
            $method = explode('::', $target);
            $unknownMethod = count($method) === 2 && ($method[1] === '' || !isset($this->engine->context->index->program->classes()[strtolower($method[0])]));
            if ($target === '' || $unknownMethod) {
                return $this->engine->context->reference($caller, 'call-path:' . $callee->body->symbol, $call->source, reason: 'UNRESOLVED_DISPATCH');
            }
            if ((new CallableIdentity())->key($target) !== (new CallableIdentity())->key($callee->body->symbol)) {
                return new Term(TermKind::Choice);
            }
            $calls = new Calls($this->engine);
            $bound = $calls->bind($caller, $call, $callee, $receiver, $depth);
            if ($callable?->kind === TermKind::Closure) {
                $bound = $calls->captures($bound, $callable);
            }
            $value = $next($bound);
            return $this->engine->context->provenance->wrap($value, EvidenceKind::Call, $call->source, ['caller' => $caller->graph->body->symbol, 'callee' => $callee->body->symbol, 'call_site' => $call->source->id(), 'parent_context' => $caller->identity, 'context' => $bound->identity, 'called_class' => $bound->calledClass, 'lexical_class' => $callee->body->className, 'implementation' => $callee->body->source->id()]);
        });
    }
    /**
     * Prunes an excluded source position before opening its selected value.
     * @param Closure(): Term $observe Selected value dependency
     */
    public function observe(Frame $frame, int $block, int $depth, Closure $observe): Term
    {
        $guards = new Guards($this->engine);
        $condition = $guards->at($frame, $block, Term::constant(true), $depth);
        if ($condition->kind === TermKind::Choice && $condition->operands === []) {
            return $condition;
        }
        return $guards->at($frame, $block, $observe(), $depth);
    }

}
