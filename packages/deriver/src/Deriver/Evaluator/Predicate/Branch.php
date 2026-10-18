<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Predicate;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Micro;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\EvaluationContractException;
use Override;

/**
 * Selects branch semantics through the ordinary operation dispatcher.
 * @implements Micro\MicroEvaluator<Term>
 * @visibility parent
 */
final class Branch implements Micro\MicroEvaluator
{
    /**
     * Shares query-local work and evidence.
     */
    public function __construct(private readonly Evaluator $engine, private readonly bool $evaluateDefault = false)
    {
    }

    /**
     * Offers one lazy condition input, independent of the surrounding syntax.
     */
    public function condition(Frame $frame, string $register, int $depth): Term
    {
        $site = new Micro\Site(
            'branch',
            '',
            $frame->graph->definitions[$register]->source ?? $frame->graph->body->source,
            fn (int|string $key): Term => $this->input($frame, $register, $key, $depth, false),
            fn (int|string $key): Term => $this->input($frame, $register, $key, $depth, true)
        );
        $builtin = new self($this->engine, isset($this->engine->context->evaluationEvidence[$frame->graph->body]) || ($frame->graph->definitions[$register]->operation ?? '') === 'static-initialized');
        return $this->engine->context->search->resolve(
            $frame->identity . ':branch:' . $register,
            fn (): Term => (new Micro\Dispatch())->evaluate(new Micro\Request($this->engine, $frame, null, $depth, site: $site), $builtin),
            fn (): Term => $this->engine->context->reference($frame, $register, $site->source, reason: 'CYCLE')
        );
    }

    /**
     * Default source semantics retain both branches without requesting a predicate.
     */
    #[Override]
    public function evaluate(Micro\Request $request): Term
    {
        return $this->evaluateDefault ? $request->input(0) : (new Choices())->make([[Term::constant(true), []], [Term::constant(false), []]]);
    }

    /**
     * Reads the single condition dependency only on evaluator demand.
     * @throws EvaluationContractException If an evaluator asks for an absent input
     */
    public function input(Frame $frame, string $register, int|string $key, int $depth, bool $truth): Term
    {
        if ($key !== 0) {
            throw new EvaluationContractException('Branch evaluation has only input 0.');
        }
        return $truth ? (new Truth($this->engine))->value($frame, $register, $depth) : $this->engine->value($frame, $register, $depth);
    }
}
