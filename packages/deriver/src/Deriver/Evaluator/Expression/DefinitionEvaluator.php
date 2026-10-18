<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;

/**
 * Evaluates one requested definition and retains its evidence and unresolved inputs.
 * @visibility parent
 */
final class DefinitionEvaluator
{
    /**
     * Uses the evaluator that schedules child dependencies.
     */
    public function __construct(public readonly Evaluator $engine)
    {
    }

    /**
     * Computes one definition; child requests suspend through the shared search.
     */
    public function evaluate(Frame $frame, string $register, int $depth): Term
    {
        $this->engine->context->frames[$frame->identity] = $frame;
        $instruction = $frame->graph->definitions[$register] ?? null;
        if ($instruction === null) {
            return $register === '' ? Term::constant(null) : $this->engine->context->reference($frame, $register, $frame->graph->body->source, reason: 'MISSING_DEFINITION');
        }
        if ($instruction->operation === 'constant') {
            $this->engine->context->record($frame, $instruction);
            $value = (new \Deriver\Deriver\Evaluator\Micro\Dispatch())->evaluate(new \Deriver\Deriver\Evaluator\Micro\Request($this->engine, $frame, $instruction, $depth), new \Deriver\Deriver\Evaluator\Micro\InstructionEvaluator());
            $value = $this->engine->context->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::SourceDefinition, $instruction->source, ['owner' => $frame->graph->body->symbol, 'definition' => $register, 'operation' => 'constant']);
            return $this->engine->bounded($frame, $instruction, $value);
        }
        $key = $frame->identity . ':' . $register;
        $memoized = $this->engine->context->values[$key] ?? null;
        if ($memoized !== null) {
            $this->engine->context->sharedNodeHits++;
            $this->engine->context->record($frame, $instruction);
            return $this->engine->bounded($frame, $instruction, $memoized);
        }
        if (isset($this->engine->context->active[$key])) {
            return $this->engine->context->reference($frame, $register, $instruction->source, reason: 'CYCLE', kind: \Deriver\Deriver\Evaluator\TermKind::Recursive);
        }
        $this->engine->context->active[$key] = true;
        $this->engine->context->record($frame, $instruction);
        $value = $this->engine->instruction($frame, $instruction, $depth);
        $value = $this->engine->context->provenance->wrap($value, in_array($instruction->operation, ['read', 'read-silent', 'argument'], true) ? \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ReferenceExpansion : \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Operation, $instruction->source, ['owner' => $frame->graph->body->symbol, 'definition' => $register, 'operation' => $instruction->operation, 'context' => $frame->identity]);
        $value = $this->engine->bounded($frame, $instruction, $value);
        unset($this->engine->context->active[$key]);
        $this->engine->context->values[$key] = $value;
        return $value;
    }
}
