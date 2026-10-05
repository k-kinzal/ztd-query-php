<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Recurrence;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Value\Term;

/**
 * Projects finite iterable keys and values at a demanded recurrence version.
 * @visibility root
 */
final class Iteration
{
    /**
     * Expands one finite iterator version or retains an unknown iteration dependency.
     */
    public function value(Derivation $engine, Frame $frame, Instruction $instruction, int $depth): Term
    {
        if ($instruction->operation === 'iterator') {
            return new Term('iterator', operands: [$engine->value($frame, $instruction->operands[0], $depth)]);
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
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return new Term($instruction->operation, operands: [$array], attributes: ['reason' => 'UNRESOLVED_ITERABLE']);
        }
        $key = array_keys($array->operands)[$version] ?? null;
        return match ($instruction->operation) {
            'iterate' => Term::constant($key !== null),
            'iterator-key' => Term::constant($key),
            'iterator-value' => $key === null ? Term::constant(null) : $array->operands[$key],
            default => new Term($instruction->operation, operands: [$array], attributes: ['reason' => 'UNRESOLVED_ITERATION_REFERENCE']),
        };
    }
}
