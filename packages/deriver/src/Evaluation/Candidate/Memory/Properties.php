<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Value\Term;

/**
 * Resolves constructor writes for a specific captured allocation.
 * @visibility root
 */
final class Properties
{
    /**
     * Demands the final constructor definition of one declared property.
     */
    public function allocated(Derivation $engine, Term $receiver, PropertyDeclaration $property, int $depth): ?Term
    {
        if ($receiver->kind === 'throwable') {
            return $receiver;
        }
        $owner = $engine->context->frames[(string) ($receiver->attributes['context'] ?? '')] ?? null;
        $creation = $owner->graph->definitions[(string) ($receiver->attributes['allocation'] ?? '')] ?? null;
        if ($owner === null || $creation === null) {
            return null;
        }
        $graph = $engine->context->index->graph($engine->context->index->target($owner->graph, $creation));
        if ($graph === null) {
            return null;
        }
        foreach ($graph->definitions as $instruction) {
            if ($instruction->operation !== 'write') {
                continue;
            }
            $address = $graph->definitions[$instruction->operands[0]] ?? null;
            $declared = $address === null ? null : $engine->context->index->declaredProperty($graph, $address);
            if ($declared?->className === $property->className && $declared->name === $property->name) {
                $frame = (new Calls($engine))->bind($owner, $creation, $graph);
                $engine->context->bodyExpansions++;
                $engine->context->bodies[$graph->body->symbol] = ($engine->context->bodies[$graph->body->symbol] ?? 0) + 1;
                return (new Calls($engine))->finalStorage($frame, $address->result, $depth);
            }
        }
        return null;
    }
}
