<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call;

use Deriver\Api\Project\Configuration;
use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\CallResolution;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\State;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(CallResolution::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Frontier::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\Argument::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Parameter::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ModelPrecedence::class)]
#[UsesClass(\Deriver\Internal\Model\PlanActions::class)]
#[UsesClass(\Deriver\Internal\Model\PlanCompiler::class)]
#[UsesClass(\Deriver\Internal\Model\PlanFootprints::class)]
#[UsesClass(\Deriver\Internal\Model\PlanValidation::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\SignatureIdentity::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Standard\FunctionModel::class)]
#[UsesClass(\Deriver\Standard\Library::class)]
#[UsesClass(Term::class)]
#[Small]
final class CallResolutionTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testBodyPreservesTheSemanticContract(): void
    {
        $model = new \Tests\Fake\PlanModel(
            new ModelDescriptor('example.key', '1', 'key', new Signature([new Parameter('id', 'int')])),
            new SemanticPlan([Action::returns(Expression::binary('.', Expression::literal(Term::constant('user:')), Expression::parameter('id')))]),
        );
        $session = \Tests\Fake\Analysis::session('<?php function target(){return key(3);}', new Configuration(models: [$model]));
        $result = $session->derive(new \Deriver\Api\Query\ReturnQuery('target'));
        self::assertSame('user:3', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testNameResolvesStandardNamespaceFallbackButPreservesExplicitFunctions(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new \Deriver\Internal\Solver\Machine($context);
        $state = new State();
        $instruction = new Instruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);

        $resolution = new CallResolution($context);
        $call = new Instruction('call', 'invoke', $body->source, 'result', attributes: ['fallback' => 'strlen']);
        self::assertSame('strlen', $resolution->name('N\\strlen', $call));
        self::assertSame('target', $resolution->name('target', $call));
    }
    public function testDescriptionIncludesFormalReceiverAndCapturedVersions(): void
    {
        $context = SolverFixture::context(configuration: new Configuration(dependencyVersions: ['library' => '2']));
        $descriptor = new ModelDescriptor('example', '1', 'Box::read');
        $instruction = new Instruction('call', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $description = (new CallResolution($context))->description($descriptor, 'Box::read', $instruction, null, null);
        self::assertSame('Box', $description->receiverType);
        self::assertFalse($description->arguments->evaluated);
        self::assertSame(['library' => '2'], $description->dependencyVersions);
    }
    /**
     * @param bool $replaceSource Whether the model overrides captured implementation
     * @param ModelDecision $decision Model applicability
     * @param string|null $expectedPath Selected body origin
     * @param list<string> $frontierCodes Expected unresolved boundaries
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerModelPrecedence')]
    public function testBodyHonorsSourcePrecedenceAndExplicitModelDecisions(bool $replaceSource, ModelDecision $decision, ?string $expectedPath, array $frontierCodes): void
    {
        $descriptor = new ModelDescriptor('example.resolve', '2', 'external', replaceSource:$replaceSource);
        $model = self::createStub(CallModel::class);
        $model->method('descriptor')->willReturn($descriptor);
        $model->method('describe')->willReturn($decision);
        $context = SolverFixture::context('<?php function external(){return 1;}function target(){}', configuration:new Configuration(models:[$model]));
        $site = new Instruction('site', 'invoke', new SourceRef('test', 'fixture.php', 5, 12));
        $body = (new CallResolution($context))->body('external', $site);
        self::assertSame($expectedPath, $body?->source->path);
        self::assertSame($frontierCodes, array_column($context->frontiers, 'code'));
    }

    /**
     * @return array<string,array{bool,ModelDecision,string|null,list<string>}>
     */
    public static function providerModelPrecedence(): array
    {
        $plan = new SemanticPlan([Action::returns(Expression::literal(Term::constant(2)))]);
        return [
            'source first' => [false,ModelDecision::unsupported('unused', false),'fixture.php',[]],
            'override' => [true,ModelDecision::handled($plan),'model:example.resolve@2',[]],
            'declined' => [true,ModelDecision::declined(),'fixture.php',[]],
            'unsupported fallback' => [true,ModelDecision::unsupported('overload'),'fixture.php',[]],
            'unsupported boundary' => [true,ModelDecision::unsupported('overload', false),null,['UNSUPPORTED_MODEL_CASE']],
            'contract boundary' => [true,ModelDecision::unsupported('MODEL_CONTRACT_VIOLATION: bad plan', false),null,['MODEL_CONTRACT_VIOLATION']],
        ];
    }

    public function testBodyRecordsModelVersionAndNativeArgumentSemantics(): void
    {
        $context = SolverFixture::context();
        $site = new Instruction('site', 'invoke', new SourceRef('test', 'fixture.php', 5, 12));
        $body = (new CallResolution($context))->body('strlen', $site);
        self::assertNotNull($body);
        self::assertTrue(isset($context->nativeCalls[$body]));
        self::assertContains('model:php.strlen@1', $context->assumptions);
        self::assertSame('test', $body->source->snapshotId);
        self::assertSame('model:php.strlen@1', $body->source->path);
    }

    public function testBodyLeavesUnavailableSemanticsUnresolvedWhenStandardModelsAreDisabled(): void
    {
        $context = SolverFixture::context(configuration:new Configuration(standardModels:false));
        $site = new Instruction('site', 'invoke', new SourceRef('test', 'fixture.php', 5, 12));
        self::assertNull((new CallResolution($context))->body('strlen', $site));
        self::assertSame([], $context->frontiers);
    }

    public function testBodyRejectsInvalidPlansAndKeepsTheCallSpecificFailureReason(): void
    {
        $descriptor = new ModelDescriptor('example.resolve', '2', 'external');
        $model = new \Tests\Fake\PlanModel($descriptor, new SemanticPlan([Action::returns(new Expression('invalid-opcode'))]));
        $context = SolverFixture::context(configuration:new Configuration(models:[$model]));
        $site = new Instruction('site', 'invoke', new SourceRef('test', 'fixture.php', 5, 12));
        self::assertNull((new CallResolution($context))->body('external', $site));
        self::assertSame('MODEL_CONTRACT_VIOLATION', $context->callFailures['site']);
        self::assertSame(['MODEL_CONTRACT_VIOLATION'], array_column($context->frontiers, 'code'));
        self::assertNotContains('model:example.resolve@2', $context->assumptions);
    }

    public function testBodyAllowsModelsToImplementAnExternalDeclarationUsingTheirSignature(): void
    {
        $descriptor = new ModelDescriptor('example.resolve', '2', 'external', new Signature([new Parameter('value', 'int')], returnType:'int'));
        $model = new \Tests\Fake\PlanModel($descriptor, new SemanticPlan([Action::returns(Expression::parameter('value'))]));
        $configuration = new Configuration(models:[$model]);
        $program = new \Deriver\Internal\Frontend\Php\ProjectIndex('test', new \Deriver\Api\Project\ProjectInput([new \Deriver\Api\Project\SourceFile('library.php', '<?php function external(int $value):int{}', declarationsOnly:true)]), $configuration->target);
        $context = new \Deriver\Internal\Solver\Context($program, new \Deriver\Api\Query\ReturnQuery('external'), $configuration, new \Deriver\Internal\Model\Registry($configuration));
        $body = (new CallResolution($context))->body('external', new Instruction('site', 'invoke', new SourceRef('test', 'caller.php', 0, 1)));
        self::assertNotNull($body);
        self::assertFalse($body->external);
        self::assertSame('int', $body->returnType);
        self::assertSame('value', $body->parameters[0]->name);
        self::assertContains('model:example.resolve@2', $context->assumptions);
    }

    /**
     * @param string $symbol Requested name
     * @param string|bool $fallback Captured fallback or malformed IR value
     * @param bool $standard Whether built-in semantics are enabled
     * @param string $expected Selected spelling
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerFallbacks')]
    public function testNameSelectsOnlyAvailableNamespaceFallbacks(string $symbol, string|bool $fallback, bool $standard, string $expected): void
    {
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('example.resolve', '2', 'N\\modeled'), new SemanticPlan([Action::returns(Expression::literal(Term::constant(1)))]));
        $context = SolverFixture::context('<?php function local_function(){}', configuration:new Configuration(models:[$model], standardModels:$standard));
        $site = new Instruction('site', 'invoke', new SourceRef('test', 'fixture.php', 0, 1), attributes:['fallback' => $fallback]);
        self::assertSame($expected, (new CallResolution($context))->name($symbol, $site));
    }

    /**
     * @return array<string,array{string,string|bool,bool,string}>
     */
    public static function providerFallbacks(): array
    {
        return [
            'source fallback' => ['N\\local_function','local_function',false,'local_function'],
            'model fallback' => ['Outer\\modeled','N\\modeled',false,'N\\modeled'],
            'qualified model wins' => ['n\\MODELED','local_function',false,'n\\MODELED'],
            'standard fallback' => ['N\\strlen','strlen',true,'strlen'],
            'disabled standard' => ['N\\strlen','strlen',false,'N\\strlen'],
            'absent fallback' => ['N\\missing','missing',true,'N\\missing'],
            'empty fallback' => ['N\\missing','',true,'N\\missing'],
            'invalid fallback' => ['N\\missing',false,true,'N\\missing'],
        ];
    }

    public function testDescriptionSuppliesEvaluatedActualsAndTheExplicitReceiverClass(): void
    {
        $context = SolverFixture::context(configuration:new Configuration(dependencyVersions:['library' => '3']));
        $descriptor = new ModelDescriptor('example.resolve', '2', 'Base::read', new Signature([new Parameter('value')]));
        $site = new Instruction('site', 'invoke', new SourceRef('test', 'fixture.php', 0, 1));
        $description = (new CallResolution($context))->description($descriptor, 'Base::read', $site, [new PassedArgument(Term::constant(7), 'value')], new State(), 'Child');
        self::assertSame('Base::read', $description->symbol);
        self::assertSame('Child', $description->receiverType);
        self::assertSame($descriptor->signature, $description->signature);
        self::assertSame($context->configuration->target, $description->target);
        self::assertSame(['library' => '3'], $description->dependencyVersions);
        self::assertTrue($description->arguments->evaluated);
        self::assertTrue($description->arguments->arguments['value']->supplied);
        self::assertSame(7, $description->arguments->arguments['value']->value->native());
    }
}
