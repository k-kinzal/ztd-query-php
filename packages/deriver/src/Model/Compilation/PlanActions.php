<?php

declare(strict_types=1);

namespace Deriver\Model\Compilation;

use Deriver\ControlFlow\Terminator;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;

/**
 * Lowers ordered plan effects to core memory, control, and completion operations.
 * @visibility root
 */
final class PlanActions
{
    /**
     * @param PlanCompiler $compiler Shared instruction builder
     */
    public function __construct(public readonly PlanCompiler $compiler)
    {
    }

    /**
     * Emits one action and reports whether it terminates its branch.
     * @param Action $action Ordered effect
     * @return bool Whether subsequent actions are unreachable
     * @throws InvalidInputException If the action is unsupported
     */
    public function apply(Action $action): bool
    {
        $c = $this->compiler;
        if (in_array($action->operation, ['callback', 'invoke', 'allocate'], true)) {
            (new PlanInvocations($c))->apply($action);
            return false;
        }
        $locations = array_map(fn (LocationRef $location): string => (new PlanLocations($c))->address($location), $action->locations);
        $operands = array_map(fn (Expression $operand): string => $c->expression($operand), $action->operands);
        if ($action->operation === 'return-reference') {
            $operands = [$c->emit('reference', [$locations[0]])];
        }
        if (in_array($action->operation, ['return', 'throw', 'return-reference'], true)) {
            $c->terminators[$c->current] = new Terminator($action->operation === 'throw' ? 'throw' : 'return', $operands[0] ?? '');
            return true;
        }
        if ($action->operation === 'state-write') {
            $c->emit('write', [$c->emit('model-state-address', [$operands[0]], name: $action->name), $operands[1]]);
        } elseif ($action->operation === 'write-parameter') {
            $c->emit('write', [$c->emit('local', name: $action->name), $operands[0]]);
        } elseif ($action->operation === 'location-write') {
            $c->emit('write', [$locations[0], $operands[0]]);
        } elseif ($action->operation === 'havoc') {
            $c->emit('model-havoc', $locations, $action->name, attributes: ['may-throw' => $action->mayThrow]);
        } elseif ($action->operation === 'alias') {
            $c->emit('alias', $locations);
        } elseif ($action->operation === 'choice') {
            $c->choice($action, $operands[0]);
        } else {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: unsupported action ' . $action->operation);
        }
        return false;
    }
}
