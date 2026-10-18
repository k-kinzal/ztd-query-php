<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
use Deriver\Deriver\Evaluator\Builtin\StringFunctions;
use Deriver\Deriver\Evaluator\Builtin\TypePredicates;
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
use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;
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
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionChain;
use Deriver\Deriver\Evaluator\Execution\Control\ExceptionMatch;
use Deriver\Deriver\Evaluator\Execution\Control\Handler;
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
use Deriver\Deriver\Evaluator\Execution\Operation\CallablePredicate;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation as SummaryInvocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\CallableTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\IntrinsicTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyAccessCheck;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
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
use Deriver\Deriver\Evaluator\Scalar\NumericString;
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

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(CallExecutor::class)]
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
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Binding::class)]
#[UsesClass(Capture::class)]
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
#[UsesClass(Transfer::class)]
#[UsesClass(ProviderDispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(UnknownCall::class)]
#[UsesClass(Completion::class)]
#[UsesClass(ClassNames::class)]
#[UsesClass(Context::class)]
#[UsesClass(ExceptionChain::class)]
#[UsesClass(ExceptionMatch::class)]
#[UsesClass(Handler::class)]
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
#[UsesClass(CallablePredicate::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(SummaryInvocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
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
#[UsesClass(LocationRef::class)]
#[UsesClass(FunctionEvaluator::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
#[UsesClass(StringFunctions::class)]
#[UsesClass(TypePredicates::class)]
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
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class CallExecutorTest extends TestCase
{
    public function testInstructionPreservesTheSemanticContract(): void
    {
        $source = '<?php function tagged(string $tag,int $id) { return $tag . ":" . $id; } function target() { return [tagged("user",1),tagged("item",2)]; }';
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
        self::assertSame(['user:1', 'item:2'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testInvokeBindsCapturedClosureValues(): void
    {
        $source = '<?php function target(){$x=2;$f=fn($n)=>$x+$n;$x=9;return $f(3);}';
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
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(5, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testSymbolPropagatesACompletedReferenceWriteBackToTheCaller(): void
    {
        $source = '<?php function f(&$x){$x=2;return 3;}function target(){$x=1;$r=f($x);return [$x,$r];}';
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
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([2, 3], $result->normalOutcomes[0]->values['return']->native());
    }
    public function testObjectInvokesAnObjectCallable(): void
    {
        $source = '<?php class B{function __invoke($x){return $x+1;}}function target(){$f=new B;return $f(3);}';
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
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(4, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @param string $source Independently verified callable fixture
     * @param string $expectedJson Complete normal return observations
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProvider('providerCallableForms')]
    public function testInvokePreservesCallableBindingIdentityAndCallingContext(string $source, string $expectedJson): void
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
        self::assertSame([], $result->exceptionalOutcomes);
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($expected);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected[0], $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return array<string,array{string,string}>
     */
    public static function providerCallableForms(): array
    {
        return array_map(static fn (array $fixture): array => [$fixture[0], $fixture[1]], ['magic-namespaced-function' => ['<?php namespace N{function names($a=__FUNCTION__,$b=__METHOD__){return [$a,$b,__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__];}}namespace{function target(){return N\names();}}', '[["N\\\\names","N\\\\names","N\\\\names","N\\\\names","N",""]]', '', false], 'magic-method' => ['<?php namespace N{class Box{function names($a=__FUNCTION__,$b=__METHOD__){return [$a,$b,__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__];}}}namespace{function target(){return (new N\Box)->names();}}', '[["names","N\\\\Box::names","names","N\\\\Box::names","N","N\\\\Box"]]', '', false], 'magic-closure-method' => ['<?php namespace N{class Box{function names(){return (fn()=>[__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__])();}}}namespace{function target(){return (new N\Box)->names();}}', '[["N\\\\{closure}","N\\\\{closure}","N","N\\\\Box"]]', '', false], 'magic-closure-function' => ['<?php namespace N{function names(){return (function(){return [__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__];})();}}namespace{function target(){return N\names();}}', '[["N\\\\{closure}","N\\\\{closure}","N",""]]', '', false], 'magic-global-closure' => ['<?php function target(){return (fn()=>[__FUNCTION__,__METHOD__,__NAMESPACE__,__CLASS__])();}', '[["{closure}","{closure}","",""]]', '', false], 'array-call-extra-entry' => ['<?php class Box{static function run(){return 1;}}function target(){$f=["Box","run",2];try{return $f();}catch(Error $e){return 3;}}', '[3]', '', false], 'array-call-extra-entry-capture' => ['<?php class Box{static function run(){return 1;}}function target(){$f=["Box","run",2];try{$g=$f(...);return $g();}catch(Error $e){return 3;}}', '[3]', '', false], 'array-call-wrong-keys' => ['<?php class Box{static function run(){return 1;}}function target(){$f=[1=>"Box",2=>"run"];try{return $f();}catch(Error $e){return 3;}}', '[3]', '', false], 'array-call-key-order' => ['<?php class Box{static function run(){return 1;}}function target(){$f=[1=>"run",0=>"Box"];return $f();}', '[1]', '', false], 'array-call-reference-elements' => ['<?php class Box{static function run(&$x){$x=2;return 1;}}function target(){$c="Box";$m="run";$f=[&$c,&$m];$x=0;$r=$f($x);return [$r,$x];}', '[[1,2]]', '', false], 'array-capture-reference-elements' => ['<?php class Box{static function run(&$x){$x=2;return 1;}}function target(){$c="Box";$m="run";$f=[&$c,&$m];$g=$f(...);$m="missing";$x=0;$r=$g($x);return [$r,$x];}', '[[1,2]]', '', false], 'private-escaped' => ['<?php class Box{private function hidden(&$x){$x=3;return 2;}function callback(){return $this->hidden(...);}}function target(){$f=(new Box)->callback();$a=1;return [$f($a),$a];}', '[[2,3]]', '', false], 'private-creation-error' => ['<?php class Box{private function hidden(){return 1;}}function target(){$b=new Box;try{$f=$b->hidden(...);}catch(Error $e){return 2;}return 3;}', '[2]', '', false], 'private-array-creation' => ['<?php class Box{private function hidden(){return 1;}function callback(){return [$this,"hidden"](...);}}function target(){$f=(new Box)->callback();return $f();}', '[1]', '', false], 'private-string-creation' => ['<?php class Box{private static function hidden(){return 1;}static function callback(){return "Box::hidden"(...);}}function target(){$f=Box::callback();return $f();}', '[1]', '', false], 'private-static-creation' => ['<?php class Box{private static function hidden(){return 1;}static function callback(){return self::hidden(...);}}function target(){$f=Box::callback();return $f();}', '[1]', '', false], 'inherited-self' => ['<?php class Base{static function name(){return static::class;}static function callback(){return self::name(...);}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false], 'inherited-parent' => ['<?php class Base{static function name(){return static::class;}}class Child extends Base{static function callback(){return parent::name(...);}}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false], 'inherited-instance-parent' => ['<?php class Base{function name(){return [static::class,$this->x];}}class Child extends Base{public $x=2;function callback(){return parent::name(...);}}function target(){$f=(new Child)->callback();return $f();}', '[["Child",2]]', '', false], 'explicit-base' => ['<?php class Base{static function name(){return static::class;}static function callback(){return Base::name(...);}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Base"]', '', false], 'ordinary-forward-static' => ['<?php class Base{static function name(){return static::class;}static function call(){return self::name();}}class Child extends Base{}function target(){return Child::call();}', '["Child"]', '', false], 'ordinary-call-does-not-rebind' => ['<?php class Other{static function name(){return 1;}}class Base{static function call(){Other::name();return static::class;}}class Child extends Base{}function target(){return Child::call();}', '["Child"]', '', false], 'closure-called-class' => ['<?php class Base{static function callback(){return static fn()=>static::class;}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false], 'closure-static-this' => ['<?php class Box{function callback(){return static function(){return isset($this);};}}function target(){$f=(new Box)->callback();return $f();}', '[false]', '', false], 'magic-escaped' => ['<?php class Box{public $x=1;function __call($name,$args){$this->x++;return [$name,$args,$this->x];}}function target(){$b=new Box;$f=$b->missing(...);return [$f(2),$b->x];}', '[[["missing",[2],2],2]]', '', false], 'magic-static-escaped' => ['<?php class Box{static function __callStatic($name,$args){return [$name,$args,static::class];}static function callback(){return self::missing(...);}}class Child extends Box{}function target(){$f=Child::callback();return $f(x:2);}', '[["missing",{"x":2},"Child"]]', '', false], 'invoke-object' => ['<?php class Box{function __invoke(&$x){$x=2;return 3;}}function target(){$b=new Box;$a=1;$f=$b(...);return [$f($a),$a];}', '[[3,2]]', '', false], 'closure-first-class-identity' => ['<?php function target(){$f=fn()=>1;$g=$f(...);return $f===$g;}', '[true]', '', false], 'first-class-identity' => ['<?php function f(){}function target(){$a=f(...);$b=f(...);return [$a===$a,$a===$b];}', '[[true,false]]', '', false], 'ordinary-array-private-error' => ['<?php class Box{private function hidden(){return 1;}function callback(){return [$this,"hidden"];}}function target(){$f=(new Box)->callback();try{return $f();}catch(Error $e){return 2;}}', '[2]', '', false], 'ordinary-string-inherited' => ['<?php class Base{static function name(){return static::class;}}class Child extends Base{}function target(){$f="Child::name";return $f();}', '["Child"]', '', false], 'parent-target-frozen' => ['<?php class Base{function foo(){return 1;}}class Child extends Base{function foo(){return 2;}function callback(){return parent::foo(...);}}function target(){$f=(new Child)->callback();return $f();}', '[1]', '', false], 'private-target-frozen' => ['<?php class Base{private function foo(){return 1;}function callback(){return $this->foo(...);}}class Child extends Base{function foo(){return 2;}}function target(){$f=(new Child)->callback();return $f();}', '[1]', '', false], 'caller-this-restored' => ['<?php class Base{public $x=1;function foo(){return $this->x;}function callback(){return self::foo(...);}}class Other{public $x=2;function run($f){return [$f(),$this->x];}}function target(){$f=(new Base)->callback();return (new Other)->run($f);}', '[[1,2]]', '', false], 'callable-original-this-live' => ['<?php class Box{public $x=1;function foo(){return $this->x;}}function target(){$b=new Box;$f=$b->foo(...);$b->x=2;return $f();}', '[2]', '', false], 'closure-fresh-same-site' => ['<?php function make(){return fn()=>1;}function target(){$a=make();$b=make();return [$a===$a,$a===$b];}', '[[true,false]]', '', false], 'callable-fresh-same-site' => ['<?php function f(){return 1;}function make(){return f(...);}function target(){$a=make();$b=make();return [$a===$a,$a===$b];}', '[[true,false]]', '', false], 'callable-closure-class' => ['<?php function f(){return 1;}function target(){$a=f(...);return [get_class($a),$a instanceof Closure,is_object($a),is_callable($a)];}', '[["Closure",true,true,true]]', '', false], 'namespace-fallback' => ['<?php namespace N{function build(){$f=strlen(...);return [$f("abc"),strlen("abcd")];}}namespace{function target(){return \N\build();}}', '[[3,4]]', '', false], 'captured-strict-invocation' => ['<?php declare(strict_types=1);function f(int $x){return $x;}function target(){$g=f(...);try{return $g("2");}catch(\TypeError $e){return 3;}}', '[3]', '', false], 'captured-weak-invocation' => ['<?php function f(int $x){return $x;}function target(){$g=f(...);return $g("2");}', '[2]', '', false], 'forward-after-nested-call' => ['<?php class Other{static function name(){return 1;}}class Base{static function name(){return static::class;}static function callback(){Other::name();return self::name(...);}}class Child extends Base{}function target(){$f=Child::callback();return $f();}', '["Child"]', '', false], 'closure-called-class-interleaved' => ['<?php class Base{static function callback(){return static fn()=>static::class;}}class Child extends Base{}class Other extends Base{}function target(){$a=Child::callback();$b=Other::callback();return [$a(),$b()];}', '[["Child","Other"]]', '', false]]);
    }
    public function testInvokeUnwrapsAResolvedCallableAndPropagatesStrictArgumentErrors(): void
    {
        $source = '<?php declare(strict_types=1);function helper(int $x){return $x;}function target(){}';
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
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $site = new BoundInstruction('call', 'invoke', $caller->source, 'result');
        $target = new Term(TermKind::Callable, operands: [Term::constant('helper')]);
        $paths = (new CallExecutor(new Evaluator($context)))->invoke($target, [new PassedArgument(Term::constant('2'))], new State(), $site, $caller);
        self::assertCount(1, $paths);
        self::assertSame(CompletionKind::Throw, $paths[0]->completion->kind);
        self::assertSame('TypeError', $paths[0]->completion->value?->literal);
    }
    public function testSymbolRestoresCallerLocalsAndKeepsEveryExitEffectAndExplanation(): void
    {
        $source = '<?php function helper(&$x,$flag){$x=2;if($flag){return 3;}$x=4;throw new Exception;}function target(){}';
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
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->memory->write($state->local('callerValue'), Term::constant(1));
        $state->registers['old'] = Term::constant('preserved');
        $state->evidence = ['caller:first', 'caller:second'];
        $state->controls = ['control:first', 'control:second'];
        $state->guard = ['outer' => true];
        $state->constraints = ['input' => ['min' => 0, 'max' => 10, 'equal' => null, 'excluded' => []]];
        $state->observed = true;
        $context->summaries->history = ['outer-call'];
        $site = new BoundInstruction('call', 'invoke', $caller->source, 'result');
        $paths = (new CallExecutor(new Evaluator($context)))->symbol('helper', [new PassedArgument(Term::constant(1), location: $state->local('callerValue')), new PassedArgument(Term::parameter('flag', 'bool'))], $state, $site);
        self::assertCount(2, $paths);
        self::assertEqualsCanonicalizing([CompletionKind::Normal, CompletionKind::Throw], array_column(array_column($paths, 'completion'), 'kind'));
        self::assertEqualsCanonicalizing([2, 4], array_map(static fn (State $path): int|float|string|bool|null => $path->memory->read($path->local('callerValue'))->literal, $paths));
        self::assertSame(['callerValue'], array_keys($paths[0]->locals));
        self::assertSame('preserved', $paths[0]->registers['old']->native());
        self::assertTrue($paths[0]->observed);
        self::assertTrue($paths[1]->observed);
        self::assertTrue($paths[0]->guard['outer']);
        self::assertNotSame($paths[0]->guard, $paths[1]->guard);
        self::assertSame($state->constraints['input'], $paths[0]->constraints['input']);
        self::assertContains('caller:first', $paths[0]->evidence);
        self::assertContains('caller:second', $paths[0]->evidence);
        self::assertGreaterThan(2, count($paths[0]->evidence));
        self::assertSame(array_values(array_unique($paths[0]->evidence)), $paths[0]->evidence);
        self::assertContains('control:first', $paths[0]->controls);
        self::assertContains('control:second', $paths[0]->controls);
        self::assertSame(array_values(array_unique($paths[0]->controls)), $paths[0]->controls);
        self::assertSame(['outer-call'], $context->summaries->history);
        self::assertSame(1, $state->memory->read($state->local('callerValue'))->native());
        self::assertSame(CompletionKind::Normal, $state->completion->kind);
        self::assertNotSame($paths[0]->memory, $paths[1]->memory);
    }
    public function testSymbolUsesWeakCoercionByDefaultAndOverridesOnlyCalleeLateStaticBinding(): void
    {
        $source = '<?php class Base{static function helper(int $x){return [$x,static::class,isset($this)];}}function target(){}';
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
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $state->lateStaticClass = 'Caller';
        $receiver = new Term(TermKind::Object, 'box', attributes: ['class' => 'Base']);
        $paths = (new CallExecutor(new Evaluator($context)))->symbol('Base::helper', [new PassedArgument(Term::constant('2'))], $state, new BoundInstruction('call', 'invoke', $caller->source, 'result'), $receiver, calledClass: 'Child');
        self::assertCount(1, $paths);
        self::assertSame(CompletionKind::Normal, $paths[0]->completion->kind);
        self::assertSame([2, 'Child', false], $paths[0]->registers['result']->native());
        self::assertSame('Caller', $paths[0]->lateStaticClass);
        self::assertSame('Caller', $state->lateStaticClass);
    }
    public function testObjectDispatchesReferencedArrayCallableElements(): void
    {
        $source = '<?php class Box{static function run($x){return $x+1;}}function target(){}';
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
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $state = new State();
        $class = $state->memory->allocate(Term::constant('Box'));
        $method = $state->memory->allocate(Term::constant('run'));
        $target = Term::array([new Term(TermKind::Cell, $class->root), new Term(TermKind::Cell, $method->root)]);
        $paths = (new CallExecutor(new Evaluator($context)))->object($target, [new PassedArgument(Term::constant(2))], $state, new BoundInstruction('call', 'invoke', $caller->source, 'result'), $caller);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame(CompletionKind::Normal, $paths[0]->completion->kind);
        self::assertSame(3, $paths[0]->registers['result']->native());
    }
    /**
     * @param Term $target Value without a resolved object callable
     */
    #[DataProvider('providerNonObjectTargets')]
    public function testObjectLeavesOtherCallableFormsToTheirOwnResolution(Term $target): void
    {
        $source = '<?php class Box{}function target(){}';
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
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        self::assertNull((new CallExecutor(new Evaluator($context)))->object($target, [], new State(), new BoundInstruction('call', 'invoke', $caller->source, 'result'), $caller));
        self::assertSame([], $context->frontiers);
    }
    /**
     * @return array<string,array{Term}>
     */
    public static function providerNonObjectTargets(): array
    {
        return ['plain function' => [Term::constant('target')], 'number' => [Term::constant(1)], 'symbolic spelling' => [Term::parameter('Box::run')], 'object without invoke' => [new Term(TermKind::Object, 'box', attributes: ['class' => 'Box'])], 'opaque' => [Term::opaque('unknown')]];
    }
    public function testInvokeRetainsAnOpenDispatchBoundaryForAnUnresolvedTarget(): void
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
        $caller = $context->program->callable('target');
        self::assertNotNull($caller);
        $paths = (new CallExecutor(new Evaluator($context)))->invoke(Term::parameter('callback'), [], new State(), new BoundInstruction('call', 'invoke', $caller->source, 'result'), $caller);
        self::assertCount(2, $paths);
        self::assertSame(['OPEN_DISPATCH'], array_column($context->frontiers, 'code'));
        self::assertSame('OPEN_DISPATCH', $paths[0]->registers['result']->literal);
        self::assertSame(CompletionKind::Throw, $paths[1]->completion->kind);
    }
}
