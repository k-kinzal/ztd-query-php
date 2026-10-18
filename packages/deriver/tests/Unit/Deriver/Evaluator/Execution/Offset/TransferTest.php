<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Offset;

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
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
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
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\NativeArguments;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
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
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer as PreparationTransfer;
use Deriver\Deriver\Evaluator\Execution\Call\ProviderDispatch;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind;
use Deriver\Deriver\Evaluator\Execution\Completion\Exceptional;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionChain;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\Handler;
use Deriver\Deriver\Evaluator\Execution\Control\LoopConvergence;
use Deriver\Deriver\Evaluator\Execution\Control\ObservationLimit;
use Deriver\Deriver\Evaluator\Execution\Control\StateJoin;
use Deriver\Deriver\Evaluator\Execution\Control\Unwinding;
use Deriver\Deriver\Evaluator\Execution\Demand\Cell;
use Deriver\Deriver\Evaluator\Execution\Demand\Components;
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Demand\Key;
use Deriver\Deriver\Evaluator\Execution\Demand\Table;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Offset\Address;
use Deriver\Deriver\Evaluator\Execution\Offset\Path;
use Deriver\Deriver\Evaluator\Execution\Offset\Reader;
use Deriver\Deriver\Evaluator\Execution\Offset\StringAccess;
use Deriver\Deriver\Evaluator\Execution\Offset\Strings;
use Deriver\Deriver\Evaluator\Execution\Offset\Transfer;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation as SummaryInvocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\CallableTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\CompoundAssignment;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\IntrinsicTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyAccessCheck;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyMagic;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyReference;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertySlot;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arithmetic;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Scalar\Increment;
use Deriver\Deriver\Evaluator\Scalar\IntegerConversion;
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
use Deriver\Deriver\Lowerer\Control\ConditionalLowering;
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Control\ExceptionLowering;
use Deriver\Deriver\Lowerer\Control\LoopLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\ConstantSignatures;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Metadata\PropertyDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\EffectInspection;
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
use Deriver\Deriver\Sources\Path as SourcesPath;
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
 * @covers \Deriver\Deriver\Evaluator\Execution\Offset\Transfer
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Transfer::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Constraints::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(CatchTarget::class)]
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
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(HierarchyAccess::class)]
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
#[UsesClass(Methods::class)]
#[UsesClass(Modes::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(PreparationTransfer::class)]
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
#[UsesClass(LoopConvergence::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(Havoc::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Reader::class)]
#[UsesClass(StringAccess::class)]
#[UsesClass(Strings::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(SummaryInvocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(CompoundAssignment::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
#[UsesClass(PropertyReference::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
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
#[UsesClass(CallEvaluator::class)]
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
#[UsesClass(SourcesPath::class)]
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
#[UsesClass(Exceptional::class)]
#[UsesClass(Frontier::class)]
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
#[UsesClass(ConditionalLowering::class)]
#[UsesClass(DestructuringLowering::class)]
#[UsesClass(ExceptionLowering::class)]
#[UsesClass(LoopLowering::class)]
#[UsesClass(EffectInspection::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Increment::class)]
#[UsesClass(IntegerConversion::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class TransferTest extends TestCase
{
    public function testApplyCreatesAReferenceCellForAnAbsentArrayElement(): void
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(SourcesPath::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new BoundInstruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $base = $state->memory->allocate(Term::constant(null));
        $state->addresses['base'] = $base;
        $state->offsets['slot'] = new Address('base', Term::constant('x'));
        $path = new Path($context);
        $read = new BoundInstruction('reference', 'reference', $body->source, 'result', ['slot']);
        $paths = (new Transfer(new Evaluator($context)))->apply($body, $read, $state);
        self::assertCount(1, $paths);
        self::assertSame(TermKind::Cell, $paths[0]->registers['result']->kind);
        self::assertSame(['x' => null], $paths[0]->memory->materialize($paths[0]->memory->read($base))->native());
    }
    public function testStringRejectsReferencesWithoutChangingTheContainingString(): void
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(SourcesPath::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new BoundInstruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $location = $state->memory->allocate(Term::constant('abc'));
        $access = new StringAccess($location, Term::constant(1));
        $reference = new BoundInstruction('reference', 'reference', $body->source, 'result', ['slot']);
        $paths = (new Transfer(new Evaluator($context)))->string($reference, $state, $access);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertSame('abc', $paths[0]->memory->read($location)->native());
    }
    public function testResultMarksConcreteErrorsAsExceptional(): void
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(SourcesPath::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new BoundInstruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $paths = (new Transfer(new Evaluator($context)))->result(new State(), $instruction, new Term(TermKind::Throwable, 'TypeError'));
        self::assertSame(CompletionKind::Throw, $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }
    public function testBoundaryPreservesBothExitKindsAndInvalidatesAffectedState(): void
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(SourcesPath::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $body = $context->program->callable('target');
        self::assertNotNull($body);
        $instruction = new BoundInstruction('offset', 'write', $body->source, 'result', ['slot', 'rhs']);
        $state = new State();
        $location = $state->local('value');
        $state->memory->write($location, Term::constant(1));
        $paths = (new Transfer(new Evaluator($context)))->boundary($state, $instruction, Term::opaque('OFFSET_OPERATION'));
        self::assertCount(2, $paths);
        self::assertSame(CompletionKind::Normal, $paths[0]->completion->kind);
        self::assertSame(CompletionKind::Throw, $paths[1]->completion->kind);
        self::assertSame(1, $paths[0]->memory->read($location)->native());
        self::assertSame(['UNSUPPORTED_LANGUAGE_FEATURE'], array_column(array_values($context->frontiers), 'code'));
    }
    public function testCompoundRejectsStringOffsetsAfterCheckingTheOriginalKey(): void
    {
        $source = '<?php function target(){$a="123";$b=0;try{$a[0]+=++$b;}catch(Error $e){return [$a,$b];}return 999;}';
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame(['123', 1], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    /**
     * @throws JsonException If recorded engine observations cannot be decoded
     */
    #[DataProvider('providerOffsetSemantics')]
    public function testApplyPreservesRecordedOffsetValuesEffectsAndExceptions(string $source, string $normalJson, string $exception, bool $diagnostic): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
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
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(SourcesPath::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input1, false));
        $program = new Index($snapshotId, $trees);
        $result = (new QueryExecution($program, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program->diagnostics()))))->derive(new ReturnQuery($symbol));
        self::assertSame($expected, array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame($diagnostic, in_array('PHP_WARNING', array_column($result->frontiers, 'code'), true));
        self::assertSame([], array_diff(array_column($result->frontiers, 'code'), ['PHP_WARNING']));
    }
    /**
     * @return iterable<string,array{string,string,string,bool}>
     */
    public static function providerOffsetSemantics(): iterable
    {
        foreach (['intWrite' => ['<?php function target(){$a=4;try{$a[0]=2;}catch(Error $e){return $a;}return 999;}', '[4]', '', false], 'nullWrite' => ['<?php function target(){$a=null;$a[0]=2;return $a;}', '[[2]]', '', false], 'falseWrite' => ['<?php function target(){$a=false;$a[0]=2;return $a;}', '[[2]]', '', true], 'arrayKey' => ['<?php function target(){$a=[];try{$a[[]]=2;}catch(TypeError $e){return $a;}return 999;}', '[[]]', '', false], 'nullInvalidKey' => ['<?php function target(){$a=null;try{$a[[]]=2;}catch(TypeError $e){return $a;}return 999;}', '[[]]', '', false], 'nestedInvalidKey' => ['<?php function target(){$a=[];try{$a["x"][[]]=2;}catch(TypeError $e){return $a;}return 999;}', '[{"x":[]}]', '', false], 'nullInvalidRead' => ['<?php function target(){$a=null;try{return $a[[]];}catch(TypeError $e){return "error";}}', '[null]', '', true], 'arrayInvalidRead' => ['<?php function target(){$a=[];try{return $a[[]];}catch(TypeError $e){return "error";}}', '["error"]', '', false], 'nullInvalidIsset' => ['<?php function target(){$a=null;try{return isset($a[[]]);}catch(TypeError $e){return "error";}}', '[false]', '', false], 'arrayInvalidIsset' => ['<?php function target(){$a=[];try{return isset($a[[]]);}catch(TypeError $e){return "error";}}', '["error"]', '', false], 'intRead' => ['<?php function target(){$a=4;return $a[0];}', '[null]', '', true], 'stringRead' => ['<?php function target(){$a="abc";return [$a[0],$a[-1],$a["1"]];}', '[["a","c","b"]]', '', false], 'stringWrite' => ['<?php function target(){$a="abc";$r=($a[1]="xy");return [$r,$a];}', '[["x","axc"]]', '', true], 'stringNegativeWrite' => ['<?php function target(){$a="abc";$a[-1]="X";return $a;}', '["abX"]', '', false], 'stringPad' => ['<?php function target(){$a="a";$a[3]="Z";return $a;}', '["a  Z"]', '', false], 'stringInvalidKey' => ['<?php function target(){$a="abc";try{$a["x"]="Z";}catch(TypeError $e){return $a;}return 999;}', '["abc"]', '', false], 'stringAppend' => ['<?php function target(){$a="abc";try{$a[]="Z";}catch(Error $e){return $a;}return 999;}', '["abc"]', '', false], 'stringEmptyWrite' => ['<?php function target(){$a="abc";try{$a[1]="";}catch(Error $e){return $a;}return 999;}', '["abc"]', '', false], 'stringArrayWrite' => ['<?php function target(){$a="abc";try{$a[1]=[];}catch(TypeError $e){return $a;}return $a;}', '["aAc"]', '', true], 'appendMax' => ['<?php function target(){$a=[9223372036854775807=>1];try{$a[]=2;}catch(Error $e){return $a;}return 999;}', '[{"9223372036854775807":1}]', '', false], 'appendMaxAfterUnset' => ['<?php function target(){$a=[9223372036854775807=>1];unset($a[9223372036854775807]);$a[]=2;return $a;}', '[{"9223372036854775807":2}]', '', false], 'string-"1.0"-read' => ['<?php function target(){$s="abc";return $s["1.0"];}', '[]', 'TypeError', false], 'string-"1.0"-isset' => ['<?php function target(){$s="abc";return isset($s["1.0"]);}', '[false]', '', false], 'string-"1.0"-write' => ['<?php function target(){$s="abc";$r=($s["1.0"]="X");return [$r,$s];}', '[]', 'TypeError', false], 'string-"1x"-read' => ['<?php function target(){$s="abc";return $s["1x"];}', '["b"]', '', true], 'string-"1x"-isset' => ['<?php function target(){$s="abc";return isset($s["1x"]);}', '[false]', '', false], 'string-"1x"-write' => ['<?php function target(){$s="abc";$r=($s["1x"]="X");return [$r,$s];}', '[["X","aXc"]]', '', true], 'string-" 1 "-read' => ['<?php function target(){$s="abc";return $s[" 1 "];}', '["b"]', '', false], 'string-" 1 "-isset' => ['<?php function target(){$s="abc";return isset($s[" 1 "]);}', '[true]', '', false], 'string-" 1 "-write' => ['<?php function target(){$s="abc";$r=($s[" 1 "]="X");return [$r,$s];}', '[["X","aXc"]]', '', false], 'string-"+1"-read' => ['<?php function target(){$s="abc";return $s["+1"];}', '["b"]', '', false], 'string-"+1"-isset' => ['<?php function target(){$s="abc";return isset($s["+1"]);}', '[true]', '', false], 'string-"+1"-write' => ['<?php function target(){$s="abc";$r=($s["+1"]="X");return [$r,$s];}', '[["X","aXc"]]', '', false], 'string-"01"-read' => ['<?php function target(){$s="abc";return $s["01"];}', '["b"]', '', false], 'string-"01"-isset' => ['<?php function target(){$s="abc";return isset($s["01"]);}', '[true]', '', false], 'string-"01"-write' => ['<?php function target(){$s="abc";$r=($s["01"]="X");return [$r,$s];}', '[["X","aXc"]]', '', false], 'string-1.5-read' => ['<?php function target(){$s="abc";return $s[1.5];}', '["b"]', '', true], 'string-1.5-isset' => ['<?php function target(){$s="abc";return isset($s[1.5]);}', '[true]', '', true], 'string-1.5-write' => ['<?php function target(){$s="abc";$r=($s[1.5]="X");return [$r,$s];}', '[["X","aXc"]]', '', true], 'string-true-read' => ['<?php function target(){$s="abc";return $s[true];}', '["b"]', '', true], 'string-true-isset' => ['<?php function target(){$s="abc";return isset($s[true]);}', '[true]', '', false], 'string-true-write' => ['<?php function target(){$s="abc";$r=($s[true]="X");return [$r,$s];}', '[["X","aXc"]]', '', true], 'string-null-read' => ['<?php function target(){$s="abc";return $s[null];}', '["a"]', '', true], 'string-null-isset' => ['<?php function target(){$s="abc";return isset($s[null]);}', '[true]', '', false], 'string-null-write' => ['<?php function target(){$s="abc";$r=($s[null]="X");return [$r,$s];}', '[["X","Xbc"]]', '', true], 'string-[]-read' => ['<?php function target(){$s="abc";return $s[[]];}', '[]', 'TypeError', false], 'string-[]-isset' => ['<?php function target(){$s="abc";return isset($s[[]]);}', '[false]', '', false], 'string-[]-write' => ['<?php function target(){$s="abc";$r=($s[[]]="X");return [$r,$s];}', '[]', 'TypeError', false], 'string-"9223372036854775807"-read' => ['<?php function target(){$s="abc";return $s["9223372036854775807"];}', '[""]', '', true], 'string-"9223372036854775807"-isset' => ['<?php function target(){$s="abc";return isset($s["9223372036854775807"]);}', '[false]', '', false], 'string-"9223372036854775808"-read' => ['<?php function target(){$s="abc";return $s["9223372036854775808"];}', '[]', 'TypeError', false], 'string-"9223372036854775808"-isset' => ['<?php function target(){$s="abc";return isset($s["9223372036854775808"]);}', '[false]', '', false], 'string-bounds--5' => ['<?php function target(){$s="abc";$r=($s[-5]="X");return [$r,$s];}', '[[null,"abc"]]', '', true], 'string-bounds--4' => ['<?php function target(){$s="abc";$r=($s[-4]="X");return [$r,$s];}', '[[null,"abc"]]', '', true], 'string-bounds--3' => ['<?php function target(){$s="abc";$r=($s[-3]="X");return [$r,$s];}', '[["X","Xbc"]]', '', false], 'string-bounds-3' => ['<?php function target(){$s="abc";$r=($s[3]="X");return [$r,$s];}', '[["X","abcX"]]', '', false], 'string-bounds-4' => ['<?php function target(){$s="abc";$r=($s[4]="X");return [$r,$s];}', '[["X","abc X"]]', '', false], 'literalString' => ['<?php function target(){return "abc"[1];}', '["b"]', '', false], 'literalArrayInvalid' => ['<?php function target(){return [1][[]];}', '[]', 'TypeError', false], 'literalScalar' => ['<?php function target(){return (4)[0];}', '[null]', '', true], 'nestedStringRead' => ['<?php function target(){$a=["abc"];return $a[0][-1][0];}', '["c"]', '', false], 'nestedStringWrite' => ['<?php function target(){$a=["abc"];$a[0][-1]="X";return $a;}', '[["abX"]]', '', false], 'privateArray' => ['<?php class B{private array $x=[];}function target(){$b=new B;try{$b->x[0]=1;}catch(Error $e){return "private";}return 999;}', '["private"]', '', false], 'privateArrayIsset' => ['<?php class B{private array $x=[1];}function target(){$b=new B;return isset($b->x[0]);}', '[false]', '', false], 'typedArrayCreate' => ['<?php class B{public array $x;}function target(){$b=new B;$b->x[0]=1;return $b->x;}', '[[1]]', '', false], 'typedNullableInt' => ['<?php class B{public ?int $x;}function target(){$b=new B;try{$b->x[0]=1;}catch(TypeError $e){return isset($b->x);}return 999;}', '[false]', '', false], 'typedNullableIntNull' => ['<?php class B{public ?int $x=null;}function target(){$b=new B;try{$b->x[0]=1;}catch(TypeError $e){return $b->x;}return 999;}', '[null]', '', false], 'staticArray' => ['<?php class B{public static array $x=[1];}function target(){B::$x[]=2;return B::$x;}', '[[1,2]]', '', false], 'readonlyArray' => ['<?php class B{public readonly array $x;function __construct(){$this->x=[1];}}function target(){$b=new B;try{$b->x[0]=2;}catch(Error $e){return $b->x;}return 999;}', '[[1]]', '', false], 'arrayReference' => ['<?php function target(){$a=[];$r=&$a["x"];$r=2;return $a;}', '[{"x":2}]', '', false], 'arrayReferenceInitial' => ['<?php function target(){$a=[];$r=&$a["x"];return [$a,$r];}', '[[{"x":null},null]]', '', false], 'arrayAliasDestination' => ['<?php function target(){$a=null;$b=2;$a["x"]=&$b;$b=3;return $a;}', '[{"x":3}]', '', false], 'stringReference' => ['<?php function target(){$a="abc";try{$r=&$a[0];}catch(Error $e){return $a;}return 999;}', '["abc"]', '', false], 'stringUnset' => ['<?php function target(){$a="abc";try{unset($a[0]);}catch(Error $e){return $a;}return 999;}', '["abc"]', '', false], 'scalarUnset' => ['<?php function target(){$a=4;unset($a[0]);return $a;}', '[]', 'Error', false], 'stringIncrement' => ['<?php function target(){$a="abc";try{$a[0]++;}catch(Error $e){return $a;}return 999;}', '["abc"]', '', false], 'arrayMissingIncrement' => ['<?php function target(){$a=[];$a[0]++;return $a;}', '[[1]]', '', true], 'plainObject' => ['<?php function target(){$a=new stdClass;try{$a[0]=1;}catch(Error $e){return "object";}return 999;}', '["object"]', '', false], 'arrayFractionalKey' => ['<?php function target(){$a=[];$a[1.5]=2;return $a;}', '[{"1":2}]', '', true], 'arrayaccess:read-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["n"];return [$r,$b->items,$b->log];}', '[[2,{"x":[1],"n":2},["get:n"]]]', '', false], 'arrayaccess:write-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=($b["n"]=3);return [$r,$b->items,$b->log];}', '[[3,{"x":[1],"n":3},["set:n"]]]', '', false], 'arrayaccess:isset-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=isset($b["n"]);return [$r,$b->items,$b->log];}', '[[true,{"x":[1],"n":2},["exists:n"]]]', '', false], 'arrayaccess:coalesce-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["n"]??9;return [$r,$b->items,$b->log];}', '[[2,{"x":[1],"n":2},["exists:n","get:n"]]]', '', false], 'arrayaccess:empty-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=empty($b["n"]);return [$r,$b->items,$b->log];}', '[[false,{"x":[1],"n":2},["exists:n","get:n"]]]', '', false], 'arrayaccess:unset-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;unset($b["n"]);$r=1;return [$r,$b->items,$b->log];}', '[[1,{"x":[1]},["unset:n"]]]', '', false], 'arrayaccess:increment-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["n"]++;return [$r,$b->items,$b->log];}', '[[2,{"x":[1],"n":2},["get:n"]]]', '', true], 'arrayaccess:compound-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=($b["n"]+=3);return [$r,$b->items,$b->log];}', '[[5,{"x":[1],"n":5},["get:n","set:n"]]]', '', false], 'arrayaccess:nestedwrite-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$b["x"][0]=3;$r=1;return [$r,$b->items,$b->log];}', '[[1,{"x":[1],"n":2},["get:x"]]]', '', true], 'arrayaccess:nestedread-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["x"][0];return [$r,$b->items,$b->log];}', '[[1,{"x":[1],"n":2},["get:x"]]]', '', false], 'arrayaccess:nestedisset-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=isset($b["x"][0]);return [$r,$b->items,$b->log];}', '[[true,{"x":[1],"n":2},["exists:x","get:x"]]]', '', false], 'arrayaccess:reference-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=&$b["n"];$r=9;return [$r,$b->items,$b->log];}', '[[9,{"x":[1],"n":2},["get:n"]]]', '', true], 'arrayaccess:alias-value' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=9;$b["n"]=&$r;return [$r,$b->items,$b->log];}', '[]', 'Error', true], 'arrayaccess:read-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["n"];return [$r,$b->items,$b->log];}', '[[2,{"x":[1],"n":2},["get:n"]]]', '', false], 'arrayaccess:write-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=($b["n"]=3);return [$r,$b->items,$b->log];}', '[[3,{"x":[1],"n":3},["set:n"]]]', '', false], 'arrayaccess:isset-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=isset($b["n"]);return [$r,$b->items,$b->log];}', '[[true,{"x":[1],"n":2},["exists:n"]]]', '', false], 'arrayaccess:coalesce-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["n"]??9;return [$r,$b->items,$b->log];}', '[[2,{"x":[1],"n":2},["exists:n","get:n"]]]', '', false], 'arrayaccess:empty-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=empty($b["n"]);return [$r,$b->items,$b->log];}', '[[false,{"x":[1],"n":2},["exists:n","get:n"]]]', '', false], 'arrayaccess:unset-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;unset($b["n"]);$r=1;return [$r,$b->items,$b->log];}', '[[1,{"x":[1]},["unset:n"]]]', '', false], 'arrayaccess:increment-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["n"]++;return [$r,$b->items,$b->log];}', '[[2,{"x":[1],"n":3},["get:n"]]]', '', false], 'arrayaccess:compound-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=($b["n"]+=3);return [$r,$b->items,$b->log];}', '[[5,{"x":[1],"n":5},["get:n","set:n"]]]', '', false], 'arrayaccess:nestedwrite-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$b["x"][0]=3;$r=1;return [$r,$b->items,$b->log];}', '[[1,{"x":[3],"n":2},["get:x"]]]', '', false], 'arrayaccess:nestedread-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=$b["x"][0];return [$r,$b->items,$b->log];}', '[[1,{"x":[1],"n":2},["get:x"]]]', '', false], 'arrayaccess:nestedisset-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=isset($b["x"][0]);return [$r,$b->items,$b->log];}', '[[true,{"x":[1],"n":2},["exists:x","get:x"]]]', '', false], 'arrayaccess:reference-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=&$b["n"];$r=9;return [$r,$b->items,$b->log];}', '[[9,{"x":[1],"n":9},["get:n"]]]', '', false], 'arrayaccess:alias-ref' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function &offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function target(){$b=new Box;$r=9;$b["n"]=&$r;return [$r,$b->items,$b->log];}', '[]', 'Error', false], 'arrayaccess:unset-null' => ['<?php function target(){$a=null;unset($a[0]);return $a;}', '[null]', '', false], 'arrayaccess:unset-false' => ['<?php function target(){$a=false;unset($a[0]);return $a;}', '[false]', '', true], 'arrayaccess:unset-true' => ['<?php function target(){$a=true;unset($a[0]);return $a;}', '[]', 'Error', false], 'arrayaccess:unset-4' => ['<?php function target(){$a=4;unset($a[0]);return $a;}', '[]', 'Error', false], 'arrayaccess:unset-4.5' => ['<?php function target(){$a=4.5;unset($a[0]);return $a;}', '[]', 'Error', false], 'arrayaccess:readonly-object' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}class Holder{public readonly Box $box;function __construct(){$this->box=new Box;}}function target(){$h=new Holder;$h->box["n"]=8;return [$h->box->items,$h->box->log];}', '[[{"x":[1],"n":8},["set:n"]]]', '', false], 'arrayaccess:computed-object' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function make(){return new Box;}function target(){return make()["n"];}', '[2]', '', false], 'arrayaccess:computed-coalesce' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}function make(){return new Box;}function target(){return make()["absent"]??9;}', '[9]', '', false], 'arrayaccess:literal-coalesce' => ['<?php function target(){return []["absent"]??9;}', '[9]', '', false], 'arrayaccess:literal-empty' => ['<?php function target(){return empty([]["absent"]);}', '[true]', '', false], 'arrayaccess:literal-nested-coalesce' => ['<?php function target(){return []["absent"]["nested"]??9;}', '[9]', '', false], 'compound:local-order' => ['<?php function target(){$a=1;$a+=($a=2);return $a;}', '[4]', '', false], 'compound:array-order' => ['<?php function target(){$a=[1];$a[0]+=($a[0]=2);return $a;}', '[[4]]', '', false], 'compound:string-compound' => ['<?php function target(){$a="abc";try{$a[0].="x";}catch(Throwable $e){return get_class($e);}return $a;}', '["Error"]', '', false], 'compound:string-numeric' => ['<?php function target(){$a="123";try{$a[0]+=2;}catch(Throwable $e){return get_class($e);}return $a;}', '["Error"]', '', false], 'compound:string-rhs' => ['<?php function target(){$a="abc";$b=0;try{$a[0]+=++$b;}catch(Throwable $e){return [$b,get_class($e)];}return $a;}', '[[1,"Error"]]', '', false], 'compound:null-failed' => ['<?php function target(){$a=null;try{$a[0]+=new stdClass;}catch(Throwable $e){return [$a,get_class($e)];}return $a;}', '[[[null],"TypeError"]]', '', true], 'compound:array-failed' => ['<?php function target(){$a=[];try{$a[0]+=new stdClass;}catch(Throwable $e){return [$a,get_class($e)];}return $a;}', '[[[null],"TypeError"]]', '', true], 'compound:string-invalid-key' => ['<?php function target(){$a="123";try{$a[[]]+=2;}catch(Throwable $e){return get_class($e);}return $a;}', '["TypeError"]', '', false], 'compound:key-1.2x' => ['<?php function target(){$s="abc";return $s[\'1.2x\'];}', '[]', 'TypeError', false], 'compound:key-1e2x' => ['<?php function target(){$s="abc";return $s[\'1e2x\'];}', '[]', 'TypeError', false], 'compound:key-1e' => ['<?php function target(){$s="abc";return $s[\'1e\'];}', '["b"]', '', true], 'compound:key-1.x' => ['<?php function target(){$s="abc";return $s[\'1.x\'];}', '[]', 'TypeError', false], 'compound:key-  +001x' => ['<?php function target(){$s="abc";return $s[\'  +001x\'];}', '["b"]', '', true], 'compound:key-9223372036854775807x' => ['<?php function target(){$s="abc";return $s[\'9223372036854775807x\'];}', '[""]', '', true], 'compound:caught-inspection' => ['<?php function target(){try{1/0;}catch(DivisionByZeroError $e){$same=$e;return [get_class($e),$same===$e,$e->getCode(),$e->getPrevious(),$e instanceof Error];}return 999;}', '[["DivisionByZeroError",true,0,null,true]]', '', false], 'compound:caught-rethrow' => ['<?php function target(){try{try{1/0;}catch(DivisionByZeroError $e){throw $e;}}catch(Error $other){return get_class($other);}return 999;}', '["DivisionByZeroError"]', '', false], 'rejected:reference:[]' => ['<?php function target(){$s="abc";$r=&$s[[]];return $s;}', '[]', 'TypeError', false], 'rejected:increment:[]' => ['<?php function target(){$s="abc";$s[[]]++;return $s;}', '[]', 'TypeError', false], 'rejected:unset:[]' => ['<?php function target(){$s="abc";unset($s[[]]);return $s;}', '[]', 'Error', false], 'rejected:nested:[]' => ['<?php function target(){$s="abc";$s[[]][0]="X";return $s;}', '[]', 'TypeError', false], 'rejected:compound:[]' => ['<?php function target(){$s="abc";$s[[]].="X";return $s;}', '[]', 'TypeError', false], 'rejected:reference:"1x"' => ['<?php function target(){$s="abc";$r=&$s["1x"];return $s;}', '[]', 'Error', true], 'rejected:increment:"1x"' => ['<?php function target(){$s="abc";$s["1x"]++;return $s;}', '[]', 'Error', true], 'rejected:unset:"1x"' => ['<?php function target(){$s="abc";unset($s["1x"]);return $s;}', '[]', 'Error', false], 'rejected:nested:"1x"' => ['<?php function target(){$s="abc";$s["1x"][0]="X";return $s;}', '[]', 'Error', true], 'rejected:compound:"1x"' => ['<?php function target(){$s="abc";$s["1x"].="X";return $s;}', '[]', 'Error', true], 'rejected:reference:1.5' => ['<?php function target(){$s="abc";$r=&$s[1.5];return $s;}', '[]', 'Error', true], 'rejected:increment:1.5' => ['<?php function target(){$s="abc";$s[1.5]++;return $s;}', '[]', 'Error', true], 'rejected:unset:1.5' => ['<?php function target(){$s="abc";unset($s[1.5]);return $s;}', '[]', 'Error', false], 'rejected:nested:1.5' => ['<?php function target(){$s="abc";$s[1.5][0]="X";return $s;}', '[]', 'Error', true], 'rejected:compound:1.5' => ['<?php function target(){$s="abc";$s[1.5].="X";return $s;}', '[]', 'Error', true], 'rejected:readonly-array-object' => ['<?php class Box implements ArrayAccess {public array $items=["x"=>[1],"n"=>2];public array $log=[];public function offsetExists(mixed $key):bool{$this->log[]="exists:".$key;return isset($this->items[$key]);}public function offsetGet(mixed $key):mixed{$this->log[]="get:".$key;return $this->items[$key];}public function offsetSet(mixed $key,mixed $value):void{$this->log[]="set:".$key;$this->items[$key]=$value;}public function offsetUnset(mixed $key):void{$this->log[]="unset:".$key;unset($this->items[$key]);}}class Holder{public readonly array $boxes;function __construct(){$this->boxes=[new Box];}}function target(){$h=new Holder;$h->boxes[0]["n"]=8;return [$h->boxes[0]->items,$h->boxes[0]->log];}', '[]', 'Error', false], 'caught:fresh-identities' => ['<?php function caught(){try{1/0;}catch(Error $e){return $e;}}function target(){$objects=[];for($i=0;$i<2;$i++){$objects[]=caught();}return $objects[0]===$objects[1];}', '[false]', '', false]] as $name => $case) {
            if (!str_contains($case[0], 'ArrayAccess')) {
                yield $name => $case;
            }
        }
    }
}
