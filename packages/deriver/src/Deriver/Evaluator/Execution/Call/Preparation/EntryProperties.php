<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Exception\InvalidInputException;

/**
 * Installs explicitly supplied receiver property values as an entry object's initial state.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 * @visibility Deriver\Deriver\Execution
 */
final class EntryProperties
{
    /**
     * @param Context $context Declaration world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Writes each supplied value into the receiver's declared storage slot.
     * Unsupplied properties keep the symbolic, declared-type state of an external object.
     * @param CallableGraph $body Entry callable
     * @param Term|null $receiver Bound receiver, or null for static and function entries
     * @param array<string, Term> $properties Supplied values keyed by property name
     * @param State $state Entry state before argument binding
     * @throws InvalidInputException If there is no receiver, a property is not a declared instance property, or a value violates its declared type
     */
    public function apply(CallableGraph $body, ?Term $receiver, array $properties, State $state): void
    {
        if ($properties === []) {
            return;
        }
        if ($receiver === null || !is_string($receiver->literal)) {
            throw new InvalidInputException('Entry properties require an instance receiver: ' . $body->symbol);
        }
        $class = $receiver->attributes['class'] ?? $receiver->attributes['type'] ?? $body->className;
        $class = is_string($class) ? ltrim($class, '\\') : $body->className;
        $root = 'object:' . $receiver->literal;
        $state->memory->cells[$root] ??= Term::array([], true);
        foreach ($properties as $name => $value) {
            $property = (new PropertyLookup($this->context->program))->find($class, $body->className, $name);
            if ($property === null || $property->static) {
                throw new InvalidInputException('Entry property is not a declared instance property: ' . $class . '::$' . $name);
            }
            $type = (new TypeBinding($this->context))->scope($property->type, $property->className, $class);
            $check = (new ReferenceAssignment($this->context))->check($value, [$type], true);
            if ($check->mustFail || $check->coercion !== null) {
                throw new InvalidInputException('Entry property value does not satisfy the declared type ' . $type . ': ' . $class . '::$' . $name);
            }
            $slot = (new ObjectAccess($this->context))->slot($class, $body->className, $name);
            $state->memory->propertyTypes[$root][$slot] = $type;
            $state->memory->write(new Location($root, [$slot]), $check->value);
        }
    }
}
