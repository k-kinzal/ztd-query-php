<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Calls;
use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Invocation\Bodies;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;

/**
 * Resolves constructor writes for a specific captured allocation.
 * @visibility parent
 */
final class Properties
{
    /**
     * Demands a constructor's property effect through the shared body selection.
     */
    public function allocated(Evaluator $engine, Term $receiver, PropertyDeclaration $property, int $depth): ?Term
    {
        if (isset($receiver->attributes['copy_context'])) {
            $owner = $engine->context->frames[(string) $receiver->attributes['copy_context']] ?? null;
            $position = $owner?->graph->positions[(string) $receiver->attributes['copy_at']] ?? null;
            if ($owner !== null && $position !== null) {
                [$observation, $address] = (new PropertyEffects())->address($owner, $receiver->operands[0], $property->name);
                return (new \Deriver\Deriver\Evaluator\Expression\Storage($engine))->search($observation, $address, $position[0], $position[1], $depth);
            }
        }
        $owner = $engine->context->frames[(string) ($receiver->attributes['context'] ?? '')] ?? null;
        $creation = $owner->graph->definitions[(string) ($receiver->attributes['allocation'] ?? '')] ?? null;
        if ($owner === null || $creation === null) {
            return null;
        }
        $body = (new Bodies($engine))->select($owner, $creation, $engine->context->index->target($owner->graph, $creation), $depth);
        $graph = $body->implementation;
        if ($graph instanceof Term) {
            return $graph;
        }
        $initial = (new PropertyOrigins($engine))->initial($property, $depth);
        if ($graph === null) {
            return $initial;
        }
        $bound = (new Calls($engine))->bind($owner, $creation, $graph, $receiver, $depth);
        $frame = new Frame($bound->graph, $bound->identity . ':property:' . $property->name, $bound->bindings, [$property->name => $initial], $bound->calls, true, origin: $bound->origin, calledClass: $bound->calledClass);
        [$observation, $address] = (new PropertyEffects())->address($frame, $receiver, $property->name);
        $body->enter($engine->context);
        return (new Calls($engine))->finalStorage($observation, $address, $depth);
    }
}
