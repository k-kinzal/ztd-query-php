<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;

/**
 * Describes an independent mutable builder using ordinary state slots.
 * @visibility root
 */
final class BuilderModels
{
    /**
     * @return Configuration Explicit mutable builder semantics
     */
    public static function configuration(): Configuration
    {
        return new Configuration(models: [
            new PlanModel(new ModelDescriptor('builder.from', '1', 'MiniBuilder::from', new Signature([new Parameter('table', 'string')])), new SemanticPlan([Action::write('example.builder.table', Expression::parameter('table')), Action::returns(Expression::receiver())], writes: ['example.builder.table'])),
            new PlanModel(new ModelDescriptor('builder.table', '1', 'MiniBuilder::tableName'), new SemanticPlan([Action::returns(Expression::state('example.builder.table'))], reads: ['example.builder.table'])),
        ], stateSlots: [new StateSlot('example.builder.table', 'string')]);
    }
}
