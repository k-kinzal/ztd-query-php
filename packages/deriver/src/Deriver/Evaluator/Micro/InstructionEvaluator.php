<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Term;
use Override;

/**
 * Evaluates source instructions through the built-in operation evaluators.
 * @implements MicroEvaluator<Term>
 * @visibility parent
 */
final class InstructionEvaluator implements MicroEvaluator
{
    /**
     * Evaluates one instruction, requesting dependencies through the shared engine.
     */
    #[Override]
    public function evaluate(Request $request): Term
    {
        $engine = $request->engine;
        $frame = $request->frame;
        $instruction = $request->instruction();
        $depth = $request->depth;
        return match ($instruction->operation) {
            'constant' => (new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->instruction($instruction),
            'closure' => new Term(\Deriver\Deriver\Evaluator\TermKind::Closure, $instruction->name, attributes: ['context' => $frame->identity, 'creation' => $instruction->result]),
            'callable', 'callable-method', 'instanceof' => (new \Deriver\Deriver\Evaluator\Language\Expressions($engine))->identity($frame, $instruction, $depth),
            'copy', 'write' => $engine->value($frame, $instruction->operands[$instruction->operation === 'write' ? 1 : 0], $depth),
            'compound', 'increment' => \Deriver\Deriver\Evaluator\Memory\Mutations::value($engine, $frame, $instruction, $depth),
            'read', 'read-silent', 'reference' => (new \Deriver\Deriver\Evaluator\Expression\Storage($engine))->read($frame, $instruction, $instruction->operands[0], $depth),
            'argument' => ($instruction->attributes['address'] ?? false) === true ? (new \Deriver\Deriver\Evaluator\Expression\Storage($engine))->read($frame, $instruction, $instruction->operands[1], $depth) : $engine->value($frame, $instruction->operands[1], $depth),
            'invoke', 'invoke-method', 'invoke-static', 'new' => (new \Deriver\Deriver\Evaluator\Calls($engine))->value($frame, $instruction, $depth),
            'phi' => $engine->phi($frame, $instruction, $depth),
            'array-set', 'array-unpack' => (new \Deriver\Deriver\Evaluator\Expression\ArrayConstruction($engine))->value($frame, $instruction, $depth),
            'static-initialized' => (new \Deriver\Deriver\Evaluator\Memory\Statics())->initialized($engine, $frame, $frame->graph->definitions[$instruction->operands[0]], $depth),
            'constant-fetch' => $engine->constant($frame, $instruction, $depth),
            'class-constant' => (new \Deriver\Deriver\Evaluator\Language\Expressions($engine))->constant($frame, $instruction, $depth),
            'clone' => (new \Deriver\Deriver\Evaluator\Language\Expressions($engine))->copy($frame, $instruction, $depth),
            'iterator', 'iterate', 'iterator-value', 'iterator-key', 'iterator-address' => (new \Deriver\Deriver\Evaluator\Recurrence\Iteration())->value($engine, $frame, $instruction, $depth),
            default => $engine->expression($frame, $instruction, $depth),
        };
    }
}
