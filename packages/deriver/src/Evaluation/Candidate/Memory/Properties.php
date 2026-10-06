<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Invocation\Bodies;
use Deriver\Value\Term;

/**
 * Resolves constructor writes for a specific captured allocation.
 * @visibility root
 */
final class Properties
{
    /**
     * Demands a constructor's property effect through the shared body selection.
     */
    public function allocated(Derivation $engine, Term $receiver, PropertyDeclaration $property, int $depth): ?Term
    {
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
            return null;
        }
        foreach ($graph->definitions as $write) {
            $address = Mutations::root($graph, $write);
            $declared = $address === null ? null : $engine->context->index->declaredProperty($graph, $address);
            if (!Mutations::writes($write) || $declared?->className !== $property->className || $declared->name !== $property->name) {
                continue;
            }
            $bound = (new Calls($engine))->bind($owner, $creation, $graph);
            $frame = new Frame($bound->graph, $bound->identity . ':property:' . $property->name, $bound->bindings, [$property->name => $initial], $bound->calls, true, origin: $bound->origin);
            $body->enter($engine->context);
            return (new Calls($engine))->finalStorage($frame, $address->result, $depth);
        }
        return null;
    }
}
