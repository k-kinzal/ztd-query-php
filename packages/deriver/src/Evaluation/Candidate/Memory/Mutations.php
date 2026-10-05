<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Evaluation\Candidate\Storage;
use Deriver\Value\Increment;
use Deriver\Value\Term;

/**
 * Describes storage mutations for both origin discovery and reaching definitions.
 * @visibility root
 */
final class Mutations
{
    /**
     * Identifies operations that update a storage address.
     */
    public static function writes(Instruction $instruction): bool
    {
        return in_array($instruction->operation, ['write', 'compound', 'increment', 'unset'], true);
    }

    /**
     * Reads the expression result at the mutation's before or after position.
     */
    public static function value(Derivation $engine, Frame $frame, Instruction $write, int $depth): Term
    {
        [$block, $offset] = $frame->graph->positions[$write->result];
        $after = $write->operation !== 'increment' || ($write->attributes['post'] ?? false) !== true;
        return (new Storage($engine))->search($frame, $write->operands[0], $block, $offset + ($after ? 1 : 0), $depth);
    }

    /**
     * Shares PHP target increment semantics across scalar, element and property writes.
     */
    public static function increment(Derivation $engine, Instruction $write, Term $before): Term
    {
        return (new Choices())->apply('increment', [$before], static function (array $values) use ($write): Term {
            $operation = new Increment();
            $delta = (int) $write->attributes['delta'];
            $value = $operation->apply($values[0], $delta);
            return $operation->warning($values[0], $delta) ? new Term($value->kind, $value->literal, $value->operands, [...$value->attributes, 'reason' => 'PHP_WARNING', 'source' => $write->source->path, 'start' => $write->source->start, 'end' => $write->source->end], $value->isSecret()) : $value;
        }, $engine->context->budget->partitions);
    }

    /**
     * Finds the root address, including element updates inside a property.
     */
    public static function root(Graph $graph, Instruction $write): ?Instruction
    {
        $address = $graph->definitions[$write->operands[0] ?? ''] ?? null;
        while ($address?->operation === 'element-address') {
            $address = $graph->definitions[$address->operands[0]] ?? null;
        }
        return $address;
    }
}
