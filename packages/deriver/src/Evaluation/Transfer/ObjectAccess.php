<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\PropertyDeclaration;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Resolves property storage using object identity and lexical declaration scope.
 * @visibility root
 */
final class ObjectAccess
{
    /**
     * @param Context $context Declaration world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Locates a property without merging unrelated allocations.
     * @param Instruction $instruction Property-address instruction
     * @param State $state Path memory
     * @return Location Property address
     */
    public function address(Instruction $instruction, State $state): Location
    {
        $receiver = $state->value($instruction->operands[0] ?? '');
        $name = $state->value($instruction->operands[1] ?? '');
        $static = $instruction->operation === 'static-address';
        $class = $this->receiverClass($instruction, $receiver, $state);
        $property = $name->kind === 'constant' && is_string($name->literal) ? (new PropertyLookup($this->context->program))->find($class, $instruction->name, $name->literal) : null;
        $id = $static ? ($property->className ?? $class) : (is_string($receiver->literal) ? $receiver->literal : 'unknown');
        $root = ($static ? 'static:' : 'object:') . $id;
        if ($receiver->kind === 'enum') {
            $state->memory->cells[$root] = Term::array($receiver->operands);
        }
        if (!isset($state->memory->cells[$root])) {
            $state->memory->cells[$root] = $static && $state->memory->unknownShared !== null ? Term::opaque($state->memory->unknownShared) : Term::array([], !$static);
        }
        if ($name->kind !== 'constant' || !is_string($name->literal)) {
            return new Location($root, unknown: true);
        }
        $state->properties[$instruction->result] = new PropertySlot($receiver, $name->literal, $instruction->name, $property, $static);
        $slot = $this->slot($class, $instruction->name, $name->literal);
        $this->prepare($state, $root, $slot, $receiver, $property);
        return new Location($root, [$slot]);
    }

    /**
     * Keeps private properties with the same spelling in separate slots.
     * @param string $class Runtime class
     * @param string $scope Lexical access scope
     * @param string $property Property name
     * @return string Declared storage slot
     */
    public function slot(string $class, string $scope, string $property): string
    {
        $definition = (new PropertyLookup($this->context->program))->find($class, $scope, $property);
        return $definition?->visibility === 'private' ? $definition->className . '::' . $property : $property;
    }

    /**
     * Registers live declaration constraints and an external receiver's initial field value.
     * @param State $state Current path
     * @param string $root Storage root
     * @param string $slot Declared storage key
     * @param Term $receiver Evaluated receiver
     * @param PropertyDeclaration|null $property Captured declaration
     */
    public function prepare(State $state, string $root, string $slot, Term $receiver, ?PropertyDeclaration $property): void
    {
        if ($property !== null && $property->type !== 'mixed') {
            $state->memory->propertyTypes[$root][$slot] = (new TypeBinding($this->context))->scope($property->type, $property->className, $state->lateStaticClass);
        }
        $record = $state->memory->cells[$root];
        if (!isset($record->operands[$slot]) && $receiver->kind === 'parameter') {
            $state->memory->write(new Location($root, [$slot]), new Term('external', $root . ':' . $slot, attributes: ['type' => $property->type ?? 'mixed', 'stability' => 'state', 'maybeUninitialized' => true]));
        }
    }

    /**
     * Separates lexical static names, runtime class strings, and object classes.
     * @param Instruction $instruction Access syntax and lexical scope
     * @param Term $receiver Evaluated class or object operand
     * @param State $state Runtime called class
     * @return string Lookup class or an unresolved empty name
     */
    public function receiverClass(Instruction $instruction, Term $receiver, State $state): string
    {
        $static = $instruction->operation === 'static-address';
        $class = $static && $receiver->kind === 'constant' && is_string($receiver->literal) ? $receiver->literal : ($receiver->attributes['class'] ?? $receiver->attributes['type'] ?? '');
        $class = is_string($class) ? $class : '';
        return !$static || ($instruction->attributes['literal-class'] ?? true) === true ? (new Dispatch($this->context->program))->className($class, $instruction->name, $state->lateStaticClass) : ltrim($class, '\\');
    }
}
