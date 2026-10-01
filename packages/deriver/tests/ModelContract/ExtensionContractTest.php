<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
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
use Tests\Fake\BuilderModels;
use Tests\Fake\MiniContainer;
use Tests\Fake\PlanModel;

/**
 * Third-party models exercise the same memory, callback, and query machinery as source.
 */
#[CoversNothing]
#[Small]
final class ExtensionContractTest extends TestCase
{
    /**
     * Verifies builder aliases and clone use independent slots.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBuilderAliasesAndCloneUseIndependentSlots(): void
    {
        $session = Analysis::session('<?php class MiniBuilder{}function target(){$a=new MiniBuilder;$a->from("users");$b=$a;$b->from("admins");$c=clone $a;$c->from("events");return [$a->tableName(),$b->tableName(),$c->tableName()];}', BuilderModels::configuration());
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(['admins', 'admins', 'events'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * Verifies container contributes declarations environment and entries.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testContainerContributesDeclarationsEnvironmentAndEntries(): void
    {
        $session = Analysis::session('<?php function entry(){return getenv("APP_NAME");}', new Configuration(providers: [new MiniContainer()]));
        $result = $session->derive($session->observations()['entry-return']);
        self::assertSame('captured', $result->normalOutcomes[0]->values['return']->native());
        self::assertArrayHasKey('generated/service.php', $session->snapshot()->sources);
        self::assertSame('1', $session->snapshot()->models['provider:example.container']);
    }

    /**
     * Verifies container dispatch completeness is explicit.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testContainerDispatchCompletenessIsExplicit(): void
    {
        $session = Analysis::session('<?php interface Contract{function label();}function target(Contract $service){return $service->label();}', new Configuration(providers: [new MiniContainer()]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('service', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertContains('container:export-complete', $result->assumptions);
    }

    /**
     * Verifies callback effects and exceptions flow through the source machine.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testCallbackEffectsAndExceptionsFlowThroughTheSourceMachine(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.apply', '1', 'apply', new Signature([new Parameter('value'), new Parameter('callback')])), new SemanticPlan([Action::callback('result', Expression::parameter('callback'), [Expression::parameter('value')]), Action::returns(Expression::parameter('result'))]));
        $session = Analysis::session('<?php function target(){$state="before";try{apply(3,function($x)use(&$state){$state="changed";throw new RuntimeException();});}catch(RuntimeException $e){return $state;}}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('changed', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }

    /**
     * Verifies undeclared state write is a contract failure.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testUndeclaredStateWriteIsAContractFailure(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.broken', '1', 'MiniBuilder::broken'), new SemanticPlan([Action::write('unlisted.slot', Expression::receiver()), Action::returns(Expression::receiver())]));
        $session = Analysis::session('<?php class MiniBuilder{}function target(){return (new MiniBuilder)->broken();}', new Configuration(models: [$model]));
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        $session->derive(new ReturnQuery('target'));
    }

    /**
     * Model signatures select reference arguments before any eager property read.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReferenceModelInitializesNullablePropertiesThroughItsDeclaredBinding(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.set', '1', 'set', new Signature([new Parameter('value', byReference: true)])), new SemanticPlan([new Action('write-parameter', [Expression::literal(Term::constant(2))], 'value'), Action::returns(Expression::literal(Term::constant(null)))], writes: ['parameter:value']));
        $session = Analysis::session('<?php class Box{public ?int $value;}function target(){$box=new Box;set($box->value);return $box->value;}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }

    /**
     * An explicit replacement supplies both the argument signature and the body.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testReplacementSignatureControlsReferencePreparation(): void
    {
        $model = new PlanModel(new ModelDescriptor('example.set', '1', 'set', new Signature([new Parameter('value', byReference: true)]), replaceSource: true), new SemanticPlan([new Action('write-parameter', [Expression::literal(Term::constant(2))], 'value'), Action::returns(Expression::literal(Term::constant(null)))], writes: ['parameter:value']));
        $session = Analysis::session('<?php function set($value){}function target(){set($value);return $value;}', new Configuration(models: [$model]));
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
}
