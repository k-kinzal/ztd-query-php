<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Plan;

use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Exception\InvalidInputException;

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
     * @throws InvalidInputException If an operand or storage descriptor is malformed
     */
    public function apply(Action $action): bool
    {
        $c = $this->compiler;
        if (in_array($action->operation, [ActionKind::Callback, ActionKind::Invoke, ActionKind::Allocate], true)) {
            (new PlanInvocations($c))->apply($action);
            return false;
        }
        $locations = array_map(fn (LocationRef $location): string => (new PlanLocations($c))->address($location), $action->locations);
        $operands = array_map(fn (Expression $operand): string => $c->expression($operand), $action->operands);
        if ($action->operation === ActionKind::ReturnReference) {
            $operands = [$c->emit('reference', [$locations[0]])];
        }
        if (in_array($action->operation, [ActionKind::Return, ActionKind::Throw, ActionKind::ReturnReference], true)) {
            $c->terminators[$c->current] = new Terminator($action->operation === ActionKind::Throw ? \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Throw : \Deriver\Deriver\Lowerer\Graph\TerminatorKind::Return, $operands[0] ?? '');
            return true;
        }
        if ($action->operation === ActionKind::StateWrite) {
            $c->emit('write', [$c->emit('evaluator-state-address', [$operands[0]], name: $action->name), $operands[1]]);
        } elseif ($action->operation === ActionKind::WriteParameter) {
            $c->emit('write', [$c->emit('local', name: $action->name), $operands[0]]);
        } elseif ($action->operation === ActionKind::LocationWrite) {
            $c->emit('write', [$locations[0], $operands[0]]);
        } elseif ($action->operation === ActionKind::Havoc) {
            $c->emit('evaluator-havoc', $locations, $action->name, attributes: ['may-throw' => $action->mayThrow]);
        } elseif ($action->operation === ActionKind::Alias) {
            $c->emit('alias', $locations);
        } elseif ($action->operation === ActionKind::Choice) {
            $c->choice($action, $operands[0]);
        }
        return false;
    }
}
