<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Enumeration;

use Deriver\Deriver\Evaluator\Scope\Context;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Retains already captured syntax at interruption without resuming dependency search.
 * @visibility parent
 */
final class Suspension
{
    private int $remaining = 128;

    /**
     * Retains a bounded syntax fragment and positioned references after expansion stops.
     */
    public function expression(Context $context, Frame $frame, Instruction $instruction, string $reason): Term
    {
        if ($instruction->operation === 'constant') {
            return $context->provenance->wrap((new \Deriver\Deriver\Evaluator\Expression\LiteralEvaluator())->instruction($instruction), \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::SourceDefinition, $instruction->source, ['owner' => $frame->graph->body->symbol, 'definition' => $instruction->result, 'operation' => 'constant']);
        }
        if (in_array($instruction->operation, ['read', 'read-silent'], true) || $instruction->operation === 'argument' && ($instruction->attributes['address'] ?? false) === true) {
            $address = $frame->graph->definitions[$instruction->operands[$instruction->operation === 'argument' ? 1 : 0]];
            $type = 'mixed';
            foreach ($frame->graph->body->parameters as $parameter) {
                if ($parameter->name === $address->name) {
                    $type = $parameter->type;
                }
            }
            return $context->reference($frame, '$' . $address->name, $instruction->source, $type, $reason, \Deriver\Deriver\Evaluator\TermKind::Deferred);
        }
        if (--$this->remaining < 1) {
            return $context->reference($frame, $instruction->operation . ':' . $instruction->name, $instruction->source, reason: $reason, kind: \Deriver\Deriver\Evaluator\TermKind::Deferred);
        }
        $values = [];
        foreach (array_values(array_unique([...$instruction->operands, ...array_map(static fn ($argument): string => $argument->register, $instruction->arguments)])) as $operand) {
            $child = $frame->graph->definitions[$operand] ?? null;
            if ($child !== null) {
                $values[] = $this->expression($context, $frame, $child, $reason);
            }
        }
        if (in_array($instruction->operation, ['argument', 'copy'], true) && $values !== []) {
            return $values[count($values) - 1];
        }
        $kind = $instruction->operation === 'binary' && $instruction->name === '.' ? \Deriver\Deriver\Evaluator\TermKind::Concat : \Deriver\Deriver\Evaluator\TermKind::Operation;
        return new Term($kind, $instruction->name, $values, ['reason' => $reason, 'source' => $instruction->source->path, 'start' => $instruction->source->start, 'end' => $instruction->source->end]);
    }
}
