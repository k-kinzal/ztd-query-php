<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Predicate;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Micro;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Override;

/**
 * A separate demand domain for predicates; full value caches are never truncated.
 * @implements Micro\MicroEvaluator<Term>
 * @visibility parent
 */
final class Truth implements Micro\MicroEvaluator
{
    /**
     * Shares normal operation dispatch and query budgets.
     */
    public function __construct(private readonly Evaluator $engine, private readonly int $wanted = 3, private readonly ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null)
    {
    }

    /**
     * Resolves only boolean possibilities for one register.
     */
    public function value(Frame $frame, string $register, int $depth): Term
    {
        return $this->engine->context->search->resolve(
            $frame->identity . ':truth:' . $this->wanted . ':' . ($this->finite->identity ?? '') . ':' . $register,
            fn (): Term => $this->definition($frame, $register, $depth),
            fn (): Term => $this->engine->context->reference($frame, $register, $frame->graph->body->source, reason: 'CYCLE', kind: TermKind::Recursive)
        );
    }

    /**
     * Offers the same operation to replacements before applying domain-specific built-ins.
     */
    public function definition(Frame $frame, string $register, int $depth): Term
    {
        $definition = $frame->graph->definitions[$register] ?? null;
        $context = $this->engine->context;
        if ($definition === null || $context->boundary($depth) !== null) {
            return (new Projection($context->provenance, $this->finite))->value($this->engine->value($frame, $register, $depth));
        }
        if (($reason = $context->work()) !== null) {
            return $context->reference($frame, $register, $definition->source, reason: $reason, kind: TermKind::Deferred);
        }
        $context->frames[$frame->identity] = $frame;
        $context->record($frame, $definition);
        $value = (new Micro\Dispatch())->evaluate(new Micro\Request($this->engine, $frame, $definition, $depth), $this);
        $value = $this->engine->bounded($frame, $definition, $value);
        return $context->provenance->wrap((new Projection($context->provenance, $this->finite))->value($value), \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Operation, $definition->source, ['owner' => $frame->graph->body->symbol, 'definition' => $register, 'operation' => $definition->operation, 'demand' => $this->finite === null ? 'truth' : 'finite']);
    }

    /**
     * Pushes boolean demand through alternatives and lazy storage and call bindings.
     */
    #[Override]
    public function evaluate(Micro\Request $request): Term
    {
        $instruction = $request->instruction();
        $frame = $request->frame;
        $depth = $request->depth;
        if ($instruction->operation === 'phi') {
            return (new \Deriver\Deriver\Evaluator\Expression\ConditionalEvaluator($this->engine))->evaluate($frame, $instruction, $depth, true, $this->wanted, $this->finite);
        }
        if (in_array($instruction->operation, ['copy', 'write'], true)) {
            return $this->value($frame, $instruction->operands[$instruction->operation === 'write' ? 1 : 0], $depth);
        }
        if ($this->finite === null && ($instruction->operation === 'unary' && $instruction->name === '!' || $instruction->operation === 'cast' && $instruction->name === 'bool')) {
            $wanted = $instruction->name === '!' ? (($this->wanted & 1) << 1) | (($this->wanted & 2) >> 1) : $this->wanted;
            return $this->engine->operation($instruction->operation, $instruction->name, [(new self($this->engine, $wanted))->value($frame, $instruction->operands[0], $depth)]);
        }
        if (in_array($instruction->operation, ['read', 'read-silent', 'reference'], true)) {
            return (new \Deriver\Deriver\Evaluator\Expression\Storage($this->engine, true, $this->wanted, $this->finite))->read($frame, $instruction, $instruction->operands[0], $depth);
        }
        if ($instruction->operation === 'argument') {
            return ($instruction->attributes['address'] ?? false) === true
                ? (new \Deriver\Deriver\Evaluator\Expression\Storage($this->engine, true, $this->wanted, $this->finite))->read($frame, $instruction, $instruction->operands[1], $depth)
                : $this->value($frame, $instruction->operands[1], $depth);
        }
        if (in_array($instruction->operation, ['invoke', 'invoke-method', 'invoke-static', 'new'], true)) {
            return (new \Deriver\Deriver\Evaluator\Calls($this->engine, true, $this->wanted, $this->finite))->value($frame, $instruction, $depth);
        }
        if ($this->finite === null && $instruction->operation === 'iterate') {
            return $this->iteration($request);
        }
        return (new Micro\InstructionEvaluator())->evaluate($request);
    }
    /**
     * Projects source iterator availability while preserving explicit evaluator versions.
     */
    public function iteration(Micro\Request $request): Term
    {
        $frame = $request->frame;
        if (isset($this->engine->context->evaluationEvidence[$frame->graph->body])) {
            return (new Micro\InstructionEvaluator())->evaluate($request);
        }
        $iterator = $this->engine->value($frame, $request->instruction()->operands[0], $request->depth);
        $array = $iterator->operands[0] ?? $iterator;
        if ($array->kind === TermKind::Array && ($array->attributes['open'] ?? false) !== true) {
            $header = $frame->graph->positions[$request->instruction()->result][0];
            if (isset($frame->iterations[$header])) {
                return Term::constant($frame->iterations[$header] < count($array->operands));
            }
            return $array->operands === [] ? Term::constant(false) : (new \Deriver\Deriver\Evaluator\Enumeration\Choices())->make([[Term::constant(true), []], [Term::constant(false), []]]);
        }
        return new Term(TermKind::Operation, 'has-next', [$array]);
    }

}
