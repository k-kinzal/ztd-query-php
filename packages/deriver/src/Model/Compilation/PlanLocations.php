<?php

declare(strict_types=1);

namespace Deriver\Model\Compilation;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Plan\Expression;

/**
 * Lowers public model locations to the same addresses used by source references.
 * @visibility root
 */
final class PlanLocations
{
    /**
     * @param PlanCompiler $compiler Shared ordered instruction builder
     */
    public function __construct(public readonly PlanCompiler $compiler)
    {
    }

    /**
     * Emits a parameter, state, or array-element address without reading it eagerly.
     * @param LocationRef $location Declarative storage reference
     * @return string Address register
     * @throws InvalidInputException If the location descriptor is malformed
     */
    public function address(LocationRef $location): string
    {
        $c = $this->compiler;
        if ($location->kind === 'parameter') {
            return $c->emit('local', name: $location->name);
        }
        if ($location->kind === 'state' && $location->receiver !== null) {
            $receiver = $c->expression($location->receiver);
            return $c->emit('model-state-address', [$receiver], name: $location->name);
        }
        if ($location->kind === 'element' && $location->parent !== null) {
            $parent = $this->address($location->parent);
            $key = $location->key === null ? '' : $c->expression($location->key);
            return $c->emit('element-address', [$parent, $key]);
        }
        throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: invalid location ' . $location->kind);
    }

    /**
     * Recognizes addressable expressions when selecting callback reference argument modes.
     * @param Expression|LocationRef $value Argument expression
     * @return LocationRef|null Available storage, or null for a computed value
     */
    public function fromExpression(Expression|LocationRef $value): ?LocationRef
    {
        if ($value instanceof LocationRef) {
            return $value;
        }
        return match ($value->operation) {
            'parameter' => LocationRef::parameter($value->name),
            'state' => LocationRef::state($value->name, $value->operands[0] ?? Expression::receiver()),
            'location-read' => $value->location,
            default => null,
        };
    }
}
