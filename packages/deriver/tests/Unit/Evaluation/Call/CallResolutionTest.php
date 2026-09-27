<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\Argument;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Model\Inputs;
use Deriver\Evaluation\Call\ParameterBinding;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\Preparation\Arguments;
use Deriver\Evaluation\Call\Preparation\Modes;
use Deriver\Evaluation\Call\Preparation\Resolution;
use Deriver\Evaluation\Call\Preparation\Target;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Builtin\FunctionModel;
use Deriver\Model\Builtin\Library;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\Compilation\PlanActions;
use Deriver\Model\Compilation\PlanCompiler;
use Deriver\Model\Compilation\PlanFootprints;
use Deriver\Model\Compilation\PlanValidation;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ModelPrecedence;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\SignatureIdentity;
use Deriver\Model\Registration\StateRegistry;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\EntryPoint;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ResourceLimits;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\ResultRef;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Assessment;
use Deriver\Result\Derivation;
use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Result\Statistics;
use Deriver\Result\StorageSnapshot;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\GraphTemplate;
use Deriver\Source\Cache\SnapshotRebase;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Cache\SyntaxTree;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\CallLowering;
use Deriver\Source\Compilation\ExpressionLowering;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Compilation\StatementLowering;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\DeclarationScanner;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(CallResolution::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(\Deriver\Exception\ModelContractException::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionModel::class)]
#[UsesClass(Library::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ModelPrecedence::class)]
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(SignatureIdentity::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceLimits::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(Frontier::class)]
#[UsesClass(JsonText::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(GraphCache::class)]
#[UsesClass(GraphTemplate::class)]
#[UsesClass(SnapshotRebase::class)]
#[UsesClass(SyntaxCache::class)]
#[UsesClass(SyntaxTree::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(GraphBuilder::class)]
#[UsesClass(Lowering::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(SyntaxSize::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
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
        $result = $session->derive(new ReturnQuery('target'));
        self::assertSame('user:3', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testNameResolvesStandardNamespaceFallbackButPreservesExplicitFunctions(): void
    {
        $context = SolverFixture::context();
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Machine($context);
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
            'unsupported reason is not an exception' => [true,ModelDecision::unsupported('MODEL_CONTRACT_VIOLATION: bad plan', false),null,['UNSUPPORTED_MODEL_CASE']],
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
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        $this->expectExceptionMessage('expression-arity-or-opcode:invalid-opcode');
        (new CallResolution($context))->body('external', $site);
    }

    public function testBodyAllowsModelsToImplementAnExternalDeclarationUsingTheirSignature(): void
    {
        $descriptor = new ModelDescriptor('example.resolve', '2', 'external', new Signature([new Parameter('value', 'int')], returnType:'int'));
        $model = new \Tests\Fake\PlanModel($descriptor, new SemanticPlan([Action::returns(Expression::parameter('value'))]));
        $configuration = new Configuration(models:[$model]);
        $program = new ProjectIndex('test', new ProjectInput([new SourceFile('library.php', '<?php function external(int $value):int{}', declarationsOnly:true)]), $configuration->target);
        $context = new Context($program, new ReturnQuery('external'), $configuration, new Registry($configuration));
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
