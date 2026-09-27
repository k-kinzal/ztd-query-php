<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use Deriver\Reference\SourceRef;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(CallResolution::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\Argument::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Parameter::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallExecutor::class)]
#[UsesClass(\Deriver\Evaluation\Call\CallableCheck::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\Model\Inputs::class)]
#[UsesClass(\Deriver\Evaluation\Call\ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Arguments::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Modes::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Resolution::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Target::class)]
#[UsesClass(\Deriver\Evaluation\Call\Preparation\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Exception\ModelContractException::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Builtin\FunctionModel::class)]
#[UsesClass(\Deriver\Model\Builtin\Library::class)]
#[UsesClass(\Deriver\Model\CallDescription::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanActions::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanCompiler::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanFootprints::class)]
#[UsesClass(\Deriver\Model\Compilation\PlanValidation::class)]
#[UsesClass(ModelDecision::class)]
#[UsesClass(ModelDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ModelPrecedence::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(\Deriver\Model\Registration\SignatureIdentity::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Result\Serialization\JsonText::class)]
#[UsesClass(\Deriver\Result\Serialization\QueryEncoding::class)]
#[UsesClass(\Deriver\Result\Serialization\ValueGraph::class)]
#[UsesClass(\Deriver\Result\Statistics::class)]
#[UsesClass(\Deriver\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Source\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Source\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Source\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Source\Compilation\CallLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
