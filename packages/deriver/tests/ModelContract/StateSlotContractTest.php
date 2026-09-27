<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\PlanModel;
use Tests\Fake\SlotModels;

/**
 * Exercises model locations and ordered actions through the public analysis API.
 */
#[CoversNothing]
#[Small]
final class StateSlotContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testDefaultsDoNotCallMagicProperties(): void
    {
        $result = Analysis::session('<?php class Box{public $calls=0;function __get($name){$this->calls++;return "magic";}}function target(){$box=new Box;return [$box->read(),$box->{"@slot:example.slot"},$box->calls];}', SlotModels::configuration(new StateSlot('example.slot', 'string', Term::constant('initial'))))->derive(new ReturnQuery('target'));
        self::assertSame(['initial','magic',1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCopiedSlotsRemainIndependentAfterClone(): void
    {
        $result = Analysis::session('<?php class Box{}function target(){$a=new Box;$a->write(2);$b=clone $a;$b->write(3);return [$a->read(),$b->read()];}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))))->derive(new ReturnQuery('target'));
        self::assertSame([2,3], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testResetSlotsUseTheDeclaredInitializerOnClone(): void
    {
        $result = Analysis::session('<?php class Box{}function target(){$a=new Box;$a->write(2);$b=clone $a;return [$a->read(),$b->read()];}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1), 'reset')))->derive(new ReturnQuery('target'));
        self::assertSame([2,1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSharedReferencesRetainTypeConstraints(): void
    {
        $result = Analysis::session('<?php class Box{}function target(){$box=new Box;$value =& $box->reference();$value=3;try{$value="invalid";}catch(TypeError $e){}return [$box->read(),$value];}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))))->derive(new ReturnQuery('target'));
        self::assertSame([3,3], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAliasTypeErrorsRetainThePriorSlotAndSource(): void
    {
        $result = Analysis::session('<?php class Box{}function target(){$box=new Box;$value="invalid";try{$box->bind($value);}catch(TypeError $e){}return [$box->read(),$value];}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))))->derive(new ReturnQuery('target'));
        self::assertSame([1,'invalid'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnknownCallsInvalidateReachableSlots(): void
    {
        $result = Analysis::session('<?php class Box{}function target(){$box=new Box;$alias=$box;mystery($alias);return $box->read();}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))))->derive(new ReturnQuery('target'));
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->kind);
        self::assertSame('int', $result->normalOutcomes[0]->values['return']->attributes['type']);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplicitStableSlotsSurviveUnknownReceiverCalls(): void
    {
        $result = Analysis::session('<?php class Box{}function target(){$box=new Box;$alias=$box;mystery($alias);return $box->read();}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1), invalidation: 'preserve')))->derive(new ReturnQuery('target'));
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
        self::assertNotEmpty($result->frontiers);
        self::assertNotEmpty($result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSlotPolicyChangesInvalidateSnapshotIdentity(): void
    {
        $a = Analysis::session('<?php class Box{}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))));
        $b = Analysis::session('<?php class Box{}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1), 'reset')));
        self::assertNotSame($a->snapshot()->id, $b->snapshot()->id);
        self::assertArrayHasKey('slot:example.slot', $b->snapshot()->models);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplicitHavocRetainsUnrelatedValuesAndBothCompletions(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.havoc', '1', 'change', new Signature([new Parameter('value', byReference: true)])), new SemanticPlan([Action::havoc([LocationRef::parameter('value')], 'external mutation'), Action::returns(Expression::read(LocationRef::parameter('value')))], writes: ['parameter:value']));
        $result = Analysis::session('<?php function target(){$value=1;$stable=2;change($value);return [$value,$stable];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame('opaque', $result->normalOutcomes[0]->values['return']->operands[0]->kind);
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->operands[1]->native());
        self::assertNotEmpty($result->exceptionalOutcomes);
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testStateQueriesReadAbstractSlotsWithoutInvokingAGetter(): void
    {
        $session = Analysis::session('<?php class Box{}function target(){$box=new Box;$box->write(9);observe($box);}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))));
        $point = $session->callsTo('observe')[0]->beforeInvocation();
        $result = $session->derive(new StateQuery($point, 'box', Projection::stateSlot('example.slot')));
        self::assertSame(9, $result->normalOutcomes[0]->values['state']->native());
        self::assertSame([], $result->frontiers);
        self::assertStringContainsString('example.slot', $result->toJson());
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testStateProjectionOnAnUnavailableReceiverReportsItsBoundary(): void
    {
        $session = Analysis::session('<?php function target(){$box=null;observe($box);}', SlotModels::configuration(new StateSlot('example.slot', 'int', Term::constant(1))));
        $point = $session->callsTo('observe')[0]->beforeInvocation();
        $result = $session->derive(new StateQuery($point, 'box', Projection::stateSlot('example.slot')));
        self::assertSame('open', $result->assessment->closure);
        self::assertContains('UNSUPPORTED_MODEL_CASE', array_column($result->frontiers, 'code'));
    }
}
