<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
use Deriver\Deriver\Evaluator\Call\ArgumentBindings;
use Deriver\Deriver\Evaluator\Call\BoundArgument;
use Deriver\Deriver\Evaluator\Call\BoundInstruction;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\PlanActions;
use Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler;
use Deriver\Deriver\Evaluator\Call\Plan\PlanFootprints;
use Deriver\Deriver\Evaluator\Call\Plan\PlanValidation;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\Declaration\Parameter as DeclarationParameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\Allocation;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Closure\Binding;
use Deriver\Deriver\Evaluator\Execution\Call\Closure\Capture;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\NativeArguments;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Constants;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\MethodInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation as NativeInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Creation;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Methods;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Modes;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\ProviderDispatch;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionChain;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\Handler;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\IntrinsicTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Execution\DerivationResult;
use Deriver\Deriver\Execution\QueryExecution;
use Deriver\Deriver\Execution\ResultAssessment;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\AggregateLowering;
use Deriver\Deriver\Lowerer\AssignmentLowering;
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Control\ExceptionLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassConstant;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\CatchTarget;
use Deriver\Deriver\Lowerer\Graph\ExceptionRegion;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Hierarchy\Access as HierarchyAccess;
use Deriver\Deriver\Lowerer\Hierarchy\Builtins;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Lowerer\LoweringScope;
use Deriver\Deriver\Lowerer\StatementLowering;
use Deriver\Deriver\MicroEvaluatorConversion;
use Deriver\Deriver\Parser\Node\File;
use Deriver\Deriver\Parser\Parser;
use Deriver\Deriver\Parser\ParserConfig;
use Deriver\Deriver\Parser\TargetSyntax;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Resolver\AssignmentPatterns;
use Deriver\Deriver\Resolver\ClassScope;
use Deriver\Deriver\Resolver\ExpressionValidation;
use Deriver\Deriver\Resolver\MagicContext;
use Deriver\Deriver\Resolver\Resolver;
use Deriver\Deriver\Selection\QueryValidation;
use Deriver\Deriver\SnapshotIdentity;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Call\Preparation\Methods
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Methods::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
#[UsesClass(ClassConstant::class)]
#[UsesClass(ClassDeclaration::class)]
#[UsesClass(ExceptionRegion::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(PropertyDeclaration::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(Allocation::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Binding::class)]
#[UsesClass(Capture::class)]
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(HierarchyAccess::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
#[UsesClass(NativeInvocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Arguments::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionEvaluator::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(CallDescription::class)]
#[UsesClass(PlanActions::class)]
#[UsesClass(PlanCompiler::class)]
#[UsesClass(PlanFootprints::class)]
#[UsesClass(PlanValidation::class)]
#[UsesClass(CallDecision::class)]
#[UsesClass(CallDescriptor::class)]
#[UsesClass(Action::class)]
#[UsesClass(Expression::class)]
#[UsesClass(SemanticPlan::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(DispatchRequest::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
#[UsesClass(DeclarationParameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Snapshot::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(ResultRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Assessment::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationResult::class)]
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
#[UsesClass(ConstantSignatures::class)]
#[UsesClass(CallableSource::class)]
#[UsesClass(Indexer::class)]
#[UsesClass(Index::class)]
#[UsesClass(Composition::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class MethodsTest extends TestCase
{
    public function testResolveRejectsPrivateCallsBeforeEvaluatingArguments(): void
    {
        $source = '<?php class Box{private function hidden($x){}}function target(){$a=1;try{(new Box)->hidden($a=2);}catch(Error $e){return $a;}return 999;}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame(1, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], array_column($result->frontiers, 'code'));
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testDeclaredRejectsInstanceMethodsWithStaticSyntax(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target1 = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target1, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target1->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target1, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Evaluator($context);
        $state = new State();
        $instruction = new BoundInstruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);
        $method = new CallableGraph('Box::run', [], [], $body->source, className: 'Box');
        $target = (new Methods($machine))->declared($method, $instruction, $state, Term::constant('Box'), 'Box', true);
        self::assertSame('Error', $target->error);
    }
    public function testNativeRequiresAnInstanceAndObtainsGetterModes(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Evaluator($context);
        $state = new State();
        $instruction = new BoundInstruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);
        $methods = new Methods($machine);
        $getter = $methods->native($instruction, $state, new Term(TermKind::Object, 'exception', attributes: ['class' => 'Exception']), 'Exception', 'getCode', false);
        self::assertNotNull($getter);
        self::assertNotNull($getter->signature);
        self::assertSame([], $getter->signature->parameters);
        self::assertSame('Error', $methods->native($instruction, $state, Term::constant('Exception'), 'Exception', 'getCode', true)?->error);
        self::assertNull($methods->native($instruction, $state, Term::constant('Box'), 'Box', 'missing', true));
    }
    public function testMagicPassesOriginalArgumentsByValue(): void
    {
        $source = '<?php class Box{function __call($n,$a){return $a;}}function target(){$a=1;return (new Box)->missing($a,$a=2);}';
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame([1, 2], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], array_column($result->frontiers, 'code'));
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testOpenDoesNotRejectPossibleSubtypeMethods(): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $machine = new Evaluator($context);
        $state = new State();
        $instruction = new BoundInstruction('arg', 'argument', $body->source, 'result', ['prepared', 'input']);
        $methods = new Methods($machine);
        self::assertTrue($methods->open(Term::parameter('receiver', 'Box'), 'Box', false));
        self::assertFalse($methods->open(new Term(TermKind::Object, 'box', attributes: ['class' => 'Box']), 'Box', false));
        self::assertFalse($methods->open(Term::constant('Box'), 'Box', true));
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerProgramsDynamicClassProgramsmethods')]
    public function testResolveSeparatesLiteralAndRuntimeClassOperands(string $source, string $expected): void
    {
        $symbol = 'target';
        $input = new Sources([new Source('fixture.php', $source)]);
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->sourceDiagnostics);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags: JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string,array{string,string}> Method and callable fixtures
     */
    public static function providerProgramsDynamicClassProgramsmethods(): iterable
    {
        $generated = [];
        $operations = ['new' => 'return get_class(new $class);', 'method' => 'return $class::value();', 'property' => 'return $class::$value;', 'instance' => 'return $object instanceof $class;', 'first-class' => '$callable=$class::value(...);return $callable();'];
        foreach (['self', 'parent', 'static', '\self', 'SELF', 'Box', '\bOx', 'Base'] as $class) {
            foreach ($operations as $name1 => $operation) {
                $known = in_array($class, ['Box', '\bOx', 'Base'], true);
                $expected = $name1 === 'instance' ? $known ? 'true' : 'false' : (!$known ? '"Error"' : ($name1 === 'new' ? $class === 'Base' ? '"Base"' : '"Box"' : ($class === 'Base' ? '3' : '7')));
                $source = '<?php class Base{public static $value=3;public static function value(){return 3;}}class Box extends Base{public static $value=7;public static function value(){return 7;}public static function run(){$object=new Box;$class=' . var_export($class, true) . ';try{' . $operation . '}catch(Throwable $e){return get_class($e);}}}function target(){return Box::run();}';
                $generated[$class . ' ' . $name1] = [$source, $expected];
            }
        }
        foreach (['self', 'parent', 'static'] as $class) {
            foreach ($operations as $name1 => $operation) {
                $body = str_replace('$class', $class, $operation);
                $expected = $name1 === 'instance' ? 'true' : ($name1 === 'new' ? $class === 'parent' ? '"Base"' : '"Box"' : ($class === 'parent' ? '3' : '7'));
                $generated['literal ' . $class . ' ' . $name1] = ['<?php class Base{public static $value=3;static function value(){return 3;}}class Box extends Base{public static $value=7;static function value(){return 7;}static function run(){$object=new Box;' . $body . '}}function target(){return Box::run();}', $expected];
            }
        }
        $generated['method arguments remain unevaluated'] = ['<?php class Box{static function value($x){return $x;}static function run(){$log=[];$class="self";try{$class::value($log[]=1);}catch(Error $e){}return $log;}}function target(){return Box::run();}', '[]'];
        $generated['constructor arguments remain unevaluated'] = ['<?php class Box{function __construct($x){}static function run(){$log=[];$class="self";try{new $class($log[]=1);}catch(Error $e){}return $log;}}function target(){return Box::run();}', '[]'];
        $generated['static syntax on an object'] = ['<?php class Box{static function value(){return 7;}}function target(){$object=new Box;return $object::value();}', '7'];
        $generated['static syntax on an enum'] = ['<?php enum E{case A;static function value(){return 7;}}function target(){$object=E::A;return $object::value();}', '7'];
        $generated['static property on an object'] = ['<?php class Box{static $value=7;}function target(){$object=new Box;return $object::$value;}', '7'];
        $generated['canonical class after first-class acquisition'] = ['<?php class Base{static function value(){return static::class;}}class Box extends Base{}function target(){$class="\bOx";$callable=$class::value(...);return $callable();}', '"Box"'];
        $generated['object instanceof object class'] = ['<?php class Box{}function target(){$bound=new Box;return new Box instanceof $bound;}', 'true'];
        $generated['closure instanceof closure class'] = ['<?php function target(){$bound=fn()=>1;return (fn()=>2) instanceof $bound;}', 'true'];
        $generated['object instanceof array rejected'] = ['<?php class Box{}function target(){$bound=[];try{return new Box instanceof $bound;}catch(Error $e){return "Error";}}', '"Error"'];
        $generated['object instanceof integer rejected'] = ['<?php class Box{}function target(){$bound=1;try{return new Box instanceof $bound;}catch(Error $e){return "Error";}}', '"Error"'];
        $generated['object instanceof null rejected'] = ['<?php class Box{}function target(){$bound=null;try{return new Box instanceof $bound;}catch(Error $e){return "Error";}}', '"Error"'];
        $generated['scalar instanceof invalid bound short circuits'] = ['<?php function target(){$bound=1;return 0 instanceof $bound;}', 'false'];
        $generated['allocation from an object'] = ['<?php class Box{}function target(){$class=new Box;return get_class(new $class);}', '"Box"'];
        $generated['object static nonstatic method rejected'] = ['<?php class Box{function value(){return 7;}}function target(){$object=new Box;try{return $object::value();}catch(Error $e){return "Error";}}', '"Error"'];
        $generated['literal called class is canonical'] = ['<?php class Box{static function value(){return static::class;}}function target(){return bOx::value();}', '"Box"'];
        $generated['dynamic called class is canonical'] = ['<?php class Box{static function value(){return static::class;}}function target(){$class="bOx";return $class::value();}', '"Box"'];
        foreach ($generated as $name => $case) {
            if (str_contains($name, 'method') || str_contains($name, 'first-class') || str_contains($name, 'called class') || str_contains($name, 'static syntax')) {
                yield $name => $case;
            }
        }
    }
}
