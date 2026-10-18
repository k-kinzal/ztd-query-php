<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Projects finite iterable keys and values at a demanded recurrence version.
 * @visibility parent
 */
final class Iteration
{
    /**
     * Expands one finite iterator version or retains an unknown iteration dependency.
     */
    public function value(Evaluator $engine, Frame $frame, Instruction $instruction, int $depth): Term
    {
        if ($instruction->operation === 'iterator') {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::Iterator, operands: [$engine->value($frame, $instruction->operands[0], $depth)]);
        }
        $iterator = $engine->value($frame, $instruction->operands[0], $depth);
        $array = $iterator->operands[0] ?? $iterator;
        $version = 0;
        foreach ($frame->graph->definitions as $definition) {
            if ($definition->operation === 'iterate' && $definition->operands[0] === $instruction->operands[0]) {
                $header = $frame->graph->positions[$definition->result][0];
                $version = $frame->iterations[$header] ?? 0;
                break;
            }
        }
        if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($array->attributes['open'] ?? false) === true) {
            return new Term(\Deriver\Deriver\Evaluator\TermKind::from($instruction->operation), operands: [$array], attributes: ['reason' => 'UNRESOLVED_ITERABLE']);
        }
        if (!isset($engine->context->evaluationEvidence[$frame->graph->body]) && in_array($instruction->operation, ['iterator-key', 'iterator-value'], true)) {
            $values = [];
            foreach ($array->operands as $key => $value) {
                $values[] = [$instruction->operation === 'iterator-key' ? Term::constant($key) : $value, [$frame->identity . ':iterator:' . $instruction->operands[0] => serialize($key)]];
            }
            return (new \Deriver\Deriver\Evaluator\Enumeration\Choices($engine->context->provenance))->make($values);
        }
        $key = array_keys($array->operands)[$version] ?? null;
        return match ($instruction->operation) {
            'iterate' => Term::constant($key !== null),
            'iterator-key' => Term::constant($key),
            'iterator-value' => $key === null ? Term::constant(null) : $array->operands[$key],
            default => new Term(\Deriver\Deriver\Evaluator\TermKind::from($instruction->operation), operands: [$array], attributes: ['reason' => 'UNRESOLVED_ITERATION_REFERENCE']),
        };
    }
}
