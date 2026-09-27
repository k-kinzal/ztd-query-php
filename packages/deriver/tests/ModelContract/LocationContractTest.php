<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\CallArgument;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\PlanModel;

/**
 * Exercises model locations and ordered actions through the public analysis API.
 */
#[CoversNothing]
#[Small]
final class LocationContractTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReferenceElementWriteReachesTheCaller(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.mutate', '1', 'mutate', new Signature([new Parameter('items', byReference: true)])), new SemanticPlan([Action::assign(LocationRef::element(LocationRef::parameter('items'), Expression::literal(Term::constant('name'))), Expression::literal(Term::constant('after'))), Action::returns(Expression::read(LocationRef::parameter('items')))], writes: ['parameter:items']));
        $result = Analysis::session('<?php function target(){$items=["name"=>"before"];return [mutate($items),$items];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([['name' => 'after'],['name' => 'after']], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReturnedReferenceRetainsAliasedParameterCell(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.pick', '1', 'pick', new Signature([new Parameter('item', byReference: true)], byReference: true)), new SemanticPlan([Action::alias(LocationRef::parameter('@alias'), LocationRef::parameter('item')), Action::returnReference(LocationRef::parameter('@alias'))], writes: ['parameter:item']));
        $result = Analysis::session('<?php function target(){$item=1;$result =& pick($item);$result=9;return [$item,$result];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([9,9], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testNamedCallbackArgumentsPreserveReferenceModes(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.apply', '1', 'apply', new Signature([new Parameter('item', byReference: true), new Parameter('callback')])), new SemanticPlan([Action::invoke('@result', Expression::parameter('callback'), [new CallArgument(Expression::literal(Term::constant(4)), 'delta'), new CallArgument(LocationRef::parameter('item'), 'value')]), Action::returns(Expression::parameter('@result'))], writes: ['parameter:item']));
        $result = Analysis::session('<?php function target(){$item=2;$r=apply($item,function(&$value,$delta){$value+=$delta;return $value;});return [$r,$item];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([6,6], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUnpackedCallbackArgumentsRetainNamedVariadicReferences(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.apply', '1', 'apply', new Signature([new Parameter('items', byReference: true), new Parameter('callback')])), new SemanticPlan([Action::invoke('@result', Expression::parameter('callback'), [new CallArgument(LocationRef::parameter('items'), unpack: true)]), Action::returns(Expression::parameter('@result'))], writes: ['parameter:items']));
        $result = Analysis::session('<?php function target(){$items=["x"=>1,"y"=>2];$r=apply($items,function(&...$values){$values["y"]=8;return $values;});return [$r,$items];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([['x' => 1,'y' => 8],['x' => 1,'y' => 8]], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallbackReferenceResultCanBeReboundAndWritten(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.apply', '1', 'apply', new Signature([new Parameter('callback')])), new SemanticPlan([Action::invoke('@result', Expression::parameter('callback'), byReference: true), Action::assign(LocationRef::parameter('@result'), Expression::literal(Term::constant(7))), Action::returns(Expression::parameter('@result'))]));
        $result = Analysis::session('<?php function target(){$item=1;$r=apply(function &()use(&$item){return $item;});return [$r,$item];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([7,7], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallbackValueResultDoesNotRetainReturnedReference(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.apply', '1', 'apply', new Signature([new Parameter('callback')])), new SemanticPlan([Action::invoke('@result', Expression::parameter('callback')), Action::assign(LocationRef::parameter('@result'), Expression::literal(Term::constant(7))), Action::returns(Expression::parameter('@result'))]));
        $result = Analysis::session('<?php function target(){$item=1;$r=apply(function &()use(&$item){return $item;});return [$r,$item];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([7,1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAllocationsUseDefaultsConstructorsAndFreshIdentities(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.create', '1', 'create', new Signature([new Parameter('value')])), new SemanticPlan([Action::allocate('@box', Expression::literal(Term::constant('Box')), [new CallArgument(Expression::parameter('value'), 'value')]), Action::returns(Expression::parameter('@box'))]));
        $result = Analysis::session('<?php class Box{public $other=3;function __construct(public $value){$this->value++;}}function target(){$a=create(2);$b=create(2);return [$a->value,$a->other,$a===$b];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([3,3,false], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testExplicitThrowPreservesCompletedReferenceWrites(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.fail', '1', 'fail', new Signature([new Parameter('item', byReference: true)])), new SemanticPlan([Action::assign(LocationRef::parameter('item'), Expression::literal(Term::constant(5))), Action::allocate('@error', Expression::literal(Term::constant('RuntimeException'))), Action::throws(Expression::parameter('@error'))], writes: ['parameter:item']));
        $result = Analysis::session('<?php function target(){$item=1;try{fail($item);}catch(RuntimeException $e){return $item;}}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame(5, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUndeclaredReferenceReturnsFailTheModelContract(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.invalid', '1', 'invalid', new Signature([new Parameter('item', byReference: true)])), new SemanticPlan([Action::returnReference(LocationRef::parameter('item'))], writes: ['parameter:item']));
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        Analysis::session('<?php function target(){return invalid($item);}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testLegacyCallbackUsesCommonReferencePreparation(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.apply', '1', 'apply', new Signature([new Parameter('item', byReference: true), new Parameter('callback')])), new SemanticPlan([Action::callback('@result', Expression::parameter('callback'), [Expression::parameter('item')]), Action::returns(Expression::parameter('@result'))], writes: ['parameter:item']));
        $result = Analysis::session('<?php function target(){$item=1;$r=apply($item,function(&$value){return ++$value;});return [$r,$item];}', new Configuration(models: [$model]))->derive(new ReturnQuery('target'));
        self::assertSame([2,2], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testNormalizedBindingsAndDependencyVersionsReachTheModel(): void
    {
        $result = Analysis::session('<?php function target(){return metadata(extra: 4);}', new Configuration(models: [new \Tests\Fake\BindingModel()], dependencyVersions: ['example/library' => '2.1']))->derive(new ReturnQuery('target'));
        self::assertSame(['id' => 3,'supplied' => false,'rest' => ['extra' => 4],'evaluated' => true,'version' => '2.1'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testSpecializedPlansKeepTheirOwnDemandedDefinitions(): void
    {
        $result = Analysis::session('<?php function target(){return [specialized(1),specialized(2)];}', new Configuration(models: [new \Tests\Fake\SpecializingModel()]))->derive(new ReturnQuery('target'));
        self::assertSame([2,3], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
}
