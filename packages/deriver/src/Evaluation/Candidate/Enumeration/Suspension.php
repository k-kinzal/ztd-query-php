<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Enumeration;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Context;
use Deriver\Evaluation\Candidate\Evidence\Provenance;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Value\Term;

/**
 * Retains already captured syntax at interruption without resuming dependency search.
 * @visibility root
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
            return Provenance::wrap($instruction->constant ?? Term::constant(null), 'source-definition', $instruction->source, ['owner' => $frame->graph->body->symbol, 'definition' => $instruction->result, 'operation' => 'constant']);
        }
        if (in_array($instruction->operation, ['read', 'read-silent'], true) || $instruction->operation === 'argument' && ($instruction->attributes['address'] ?? false) === true) {
            $address = $frame->graph->definitions[$instruction->operands[$instruction->operation === 'argument' ? 1 : 0]];
            $type = 'mixed';
            foreach ($frame->graph->body->parameters as $parameter) {
                if ($parameter->name === $address->name) {
                    $type = $parameter->type;
                }
            }
            return $context->reference($frame, '$' . $address->name, $instruction->source, $type, $reason, 'deferred');
        }
        if (--$this->remaining < 1) {
            return $context->reference($frame, $instruction->operation . ':' . $instruction->name, $instruction->source, reason: $reason, kind: 'deferred');
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
        $kind = $instruction->operation === 'binary' && $instruction->name === '.' ? 'concat' : 'operation';
        return new Term($kind, $instruction->name, $values, ['reason' => $reason, 'source' => $instruction->source->path, 'start' => $instruction->source->start, 'end' => $instruction->source->end]);
    }
}
