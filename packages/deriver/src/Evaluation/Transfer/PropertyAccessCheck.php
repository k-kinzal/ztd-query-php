<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Checks receiver categories and readonly mutations before accessing property storage.
 * @visibility root
 */
final class PropertyAccessCheck
{
    /**
     * @param Machine $machine Captured declaration world
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Handles invalid receivers and indirect readonly writes before normal access.
     * @param Instruction $instruction Storage operation
     * @param State $state Current path
     * @param PropertySlot $slot Property context
     * @param Location $address Evaluated storage address
     * @return list<State>|null Handled alternatives, or null for ordinary access
     */
    public function apply(Instruction $instruction, State $state, PropertySlot $slot, Location $address): ?array
    {
        $read = in_array($instruction->operation, ['read', 'read-silent'], true);
        if (!$slot->static && in_array($slot->receiver->kind, ['constant', 'array'], true)) {
            if ($read) {
                if ($instruction->operation === 'read') {
                    $this->machine->context->frontier('PHP_WARNING', $instruction->source, 'property-read-on-non-object');
                }
                $state->registers[$instruction->result] = Term::constant(null);
                return [$state];
            }
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        $class = $slot->receiver->attributes['class'] ?? '';
        $readonlyClass = is_string($class) && ($this->machine->context->program->classes()[strtolower($class)]->readonly ?? false);
        $indirectReadonly = $slot->declaration?->readonly === true && $this->indirectReadonly($instruction, $state, $address);
        if (!$read && ($slot->receiver->kind === 'enum' || $readonlyClass && $slot->declaration === null || $indirectReadonly)) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        return null;
    }

    /**
     * Allows mutation through a readonly object handle while rejecting array and scalar changes.
     * @param Instruction $instruction Pending operation
     * @param State $state Captured receiver storage
     * @param Location $address Final address
     * @return bool Whether this operation would mutate the readonly property itself
     */
    public function indirectReadonly(Instruction $instruction, State $state, Location $address): bool
    {
        if (!isset($state->offsets[$instruction->operands[0]])) {
            return count($address->path) > 1;
        }
        $base = (new Path($this->machine->context))->chain($state, $instruction->operands[0])['base'];
        return $state->memory->read($base)->kind !== 'object';
    }
}
