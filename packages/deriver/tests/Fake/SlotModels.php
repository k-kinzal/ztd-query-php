<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Value\Term;

/**
 * Declares typed external state access without exposing a PHP property.
 * @visibility root
 */
final class SlotModels
{
    /**
     * @param StateSlot $slot Tested initialization and lifecycle policy
     * @return Configuration Four modeled operations over one abstract object slot
     */
    public static function configuration(StateSlot $slot): Configuration
    {
        return new Configuration(models: [
            new PlanModel(new ModelDescriptor('slot.read', '1', 'Box::read'), new SemanticPlan([Action::returns(Expression::state($slot->id))], reads: [$slot->id])),
            new PlanModel(new ModelDescriptor('slot.write', '1', 'Box::write', new Signature([new Parameter('value')])), new SemanticPlan([Action::write($slot->id, Expression::parameter('value')), Action::returns(Expression::receiver())], writes: [$slot->id])),
            new PlanModel(new ModelDescriptor('slot.reference', '1', 'Box::reference', new Signature(byReference: true)), new SemanticPlan([Action::returnReference(LocationRef::state($slot->id))], writes: [$slot->id])),
            new PlanModel(new ModelDescriptor('slot.bind', '1', 'Box::bind', new Signature([new Parameter('value', byReference: true)])), new SemanticPlan([Action::alias(LocationRef::state($slot->id), LocationRef::parameter('value')), Action::returns(Expression::literal(Term::constant(null)))], writes: [$slot->id, 'parameter:value'])),
        ], stateSlots: [$slot]);
    }
}
