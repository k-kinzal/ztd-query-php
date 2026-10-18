<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Transfer;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\BoundInstruction;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\Allocation;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Constants;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\MethodInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation as NativeInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Properties;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Creation;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Methods;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind;
use Deriver\Deriver\Evaluator\Execution\Completion\Quality\DependencyClosure;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Constant\ClassNames;
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
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation as SummaryInvocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\CallableTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ObjectAccess;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyAccessCheck;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyLookup;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyMagic;
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
use Deriver\Deriver\Lowerer\Declaration\Traits\LexicalConstants;
use Deriver\Deriver\Lowerer\Declaration\Traits\Members;
use Deriver\Deriver\Lowerer\EffectInspection;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\CatchTarget;
use Deriver\Deriver\Lowerer\Graph\ExceptionRegion;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\Graph\TerminatorKind;
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
 * @covers \Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(ConstantTransfer::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
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
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(HierarchyAccess::class)]
#[UsesClass(Constants::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(MethodInvocation::class)]
#[UsesClass(NativeInvocation::class)]
#[UsesClass(Properties::class)]
#[UsesClass(Signatures::class)]
#[UsesClass(ParameterBinding::class)]
#[UsesClass(PassedArgument::class)]
#[UsesClass(Creation::class)]
#[UsesClass(Methods::class)]
#[UsesClass(Resolution::class)]
#[UsesClass(Target::class)]
#[UsesClass(Transfer::class)]
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
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(StateStorage::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(SummaryInvocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(ObjectAccess::class)]
#[UsesClass(PropertyAccessCheck::class)]
#[UsesClass(PropertyLookup::class)]
#[UsesClass(PropertyMagic::class)]
#[UsesClass(PropertySlot::class)]
#[UsesClass(PropertyTransfer::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(DispatchDecision::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(LexicalConstants::class)]
#[UsesClass(Members::class)]
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ConstantTransferTest extends TestCase
{
    public function testApplyEvaluatesCapturedConstantExpressions(): void
    {
        $source = '<?php const BASE=20;const ANSWER=BASE*2+2;function target(){return ANSWER;}';
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
        self::assertSame(42, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testMemberChecksAccessBeforeReturningAnInitializer(): void
    {
        $source = '<?php class Box{private const A=1;}function target(){try{return Box::A;}catch(Error $e){return 2;}}';
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
        self::assertSame(2, $result->normalOutcomes[0]->values['return']->native());
    }
    public function testInitializerBuildsAnEnumFromItsBackingExpression(): void
    {
        $source = '<?php enum Flag:int{case A=1+2;}function target(){return Flag::A->value;}';
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
        self::assertSame(3, $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerProgramsClassNameProgramscases')]
    public function testMemberPreservesOracleCheckedClassNameAndConstantSemantics(string $source, string $expected): void
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
    public function testMemberKeepsUnavailableDynamicClassLookupsExplicit(): void
    {
        $source = '<?php function target(){$name="class";return Missing::{$name};}';
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
        self::assertSame(DependencyClosure::Open, $result->assessment->closure);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(TermKind::Opaque, $result->normalOutcomes[0]->values['return']->kind);
        self::assertContains('INCOMPLETE_SOURCE', array_column($result->frontiers, 'code'));
    }
    public function testFinishRetainsTheExactClassValueAndExistingState(): void
    {
        $source1 = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $state = new State();
        $source = new SourceRef('snapshot', 'class.php', 4, 18);
        $instruction = new BoundInstruction('fetch', 'class-constant', $source, 'result');
        $value = Term::constant('Box');
        $paths = (new ConstantTransfer(new Evaluator($context)))->finish($instruction, $state, $value);
        self::assertSame([$state], $paths);
        self::assertSame($value, $paths[0]->registers['result']);
        self::assertSame(CompletionKind::Normal, $paths[0]->completion->kind);
        self::assertSame([], $context->frontiers);
    }
    public function testFinishPropagatesClassResolutionErrorsWithoutWritingAValue(): void
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
        $state = new State();
        $instruction = new BoundInstruction('fetch', 'class-constant', new SourceRef('snapshot', 'class.php', 4, 18), 'result');
        $error = new Term(TermKind::Throwable, 'TypeError');
        $paths = (new ConstantTransfer(new Evaluator($context)))->finish($instruction, $state, $error);
        self::assertSame([$state], $paths);
        self::assertSame(CompletionKind::Throw, $paths[0]->completion->kind);
        self::assertSame($error, $paths[0]->completion->value);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }
    public function testFinishRecordsTheUnresolvedClassDependencyAtItsSource(): void
    {
        $source1 = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $source = new SourceRef('snapshot', 'class.php', 4, 18);
        $instruction = new BoundInstruction('fetch', 'class-constant', $source, 'result');
        $dependency = Term::parameter('class');
        $value = Term::opaque('INCOMPLETE_SOURCE', dependencies: [$dependency]);
        $paths = (new ConstantTransfer(new Evaluator($context)))->finish($instruction, new State(), $value);
        self::assertSame(TermKind::Opaque, $paths[0]->registers['result']->kind);
        self::assertSame('INCOMPLETE_SOURCE', $paths[0]->registers['result']->literal);
        self::assertCount(1, $context->frontiers);
        $frontier = array_values($context->frontiers)[0];
        self::assertSame('INCOMPLETE_SOURCE', $frontier->code);
        self::assertSame('dynamic-class-constant', $frontier->operation);
        self::assertSame($source, $frontier->at);
        self::assertNotNull($frontier->residual);
        self::assertSame([$dependency], $frontier->residual->operands);
    }
    /**
     * @param list<string> $completions Expected path completions
     * @param list<string> $kinds Result or exception categories
     * @param list<int|float|string|bool|null> $literals Result or exception values
     */
    #[DataProvider('providerInitializerResults')]
    public function testInitializerChecksDeclaredTypesAndConstructsEnumIdentity(Term $value, ?ClassConstant $constant, array $completions, array $kinds, array $literals): void
    {
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $body = new CallableGraph('Box::VALUE:initializer', [], [new BasicBlock(0, [new BoundInstruction('value', 'constant', $source, 'value', constant: $value)], new Terminator(TerminatorKind::Return, 'value'))], $source);
        $program = self::createStub(Program::class);
        $program->method('constant')->willReturn($body);
        $configuration = new DeriverConfig();
        $source1 = '<?php function target(){return 1;}';
        $budget = new Budget();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program2 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program2, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program2->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program1 = new Index($snapshotId1, $trees1);
        $contributions2 = new Contributions();
        $context = new Context($program, new ReturnQuery('target'), (new Context($program1, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators))->configuration, new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions2->evaluators, $contributions2->providers, $contributions2->domains, $contributions2->intrinsics, $contributions2->stateSlots), $configuration->standardEvaluators));
        $state = new State();
        $marker = $state->memory->allocate(Term::constant('retained'));
        $instruction = new BoundInstruction('fetch', 'class-constant', $source, 'result');
        $paths = (new ConstantTransfer(new Evaluator($context)))->initializer('Box::VALUE', $instruction, $state, $constant);
        self::assertSame($completions, array_map(static fn (State $path): string => $path->completion->kind->value, $paths));
        self::assertSame($kinds, array_map(static fn (State $path): string => ($path->completion->value ?? $path->value('result'))->kind->value, $paths));
        self::assertSame($literals, array_map(static fn (State $path) => ($path->completion->value ?? $path->value('result'))->literal, $paths));
        self::assertSame('retained', $paths[0]->memory->read($marker)->literal);
        self::assertNotSame($state, $paths[0]);
        self::assertNotSame($state->memory, $paths[0]->memory);
        self::assertArrayNotHasKey('result', $state->registers);
    }
    /**
     * @return iterable<string,array{Term,ClassConstant|null,list<string>,list<string>,list<int|float|string|bool|null>}>
     */
    public static function providerInitializerResults(): iterable
    {
        yield 'untyped global' => [Term::constant('value'), null, ['normal'], ['constant'], ['value']];
        yield 'integer' => [Term::constant(7), new ClassConstant('Box', 'VALUE', type: 'int'), ['normal'], ['constant'], [7]];
        yield 'strict scalar mismatch' => [Term::constant('7'), new ClassConstant('Box', 'VALUE', type: 'int'), ['throw'], ['throwable'], ['TypeError']];
        yield 'integer float widening' => [Term::constant(7), new ClassConstant('Box', 'VALUE', type: 'float'), ['normal'], ['constant'], [7.0]];
        yield 'symbolic type split' => [Term::parameter('value'), new ClassConstant('Box', 'VALUE', type: 'int'), ['throw', 'normal'], ['throwable', 'type-refinement'], ['TypeError', 'int']];
        yield 'backed enum' => [Term::constant('token'), new ClassConstant('Mode', 'Ready', type: 'string', enum: true), ['normal'], ['enum'], ['Mode::Ready']];
        yield 'invalid backing type' => [Term::constant('token'), new ClassConstant('Mode', 'Ready', type: 'int', enum: true), ['throw'], ['throwable'], ['TypeError']];
    }
    public function testInitializerRetainsEnumCaseNameBackingValueAndDeclaringClass(): void
    {
        $source1 = '<?php enum Mode:string{case Ready="token";} function target(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $paths = (new ConstantTransfer(new Evaluator($context)))->initializer('Mode::Ready', new BoundInstruction('fetch', 'class-constant', $source, 'result'), new State(), new ClassConstant('Mode', 'Ready', type: 'string', enum: true));
        self::assertCount(1, $paths);
        $result = $paths[0]->registers['result'];
        self::assertSame(TermKind::Enum, $result->kind);
        self::assertSame('Mode::Ready', $result->literal);
        self::assertSame(['class' => 'Mode'], $result->attributes);
        self::assertSame('Ready', $result->operands['name']->literal);
        self::assertSame('token', $result->operands['value']->literal);
    }
    public function testInitializerPropagatesFailureBeforeEnforcingTheConstantType(): void
    {
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $error = new Term(TermKind::Throwable, 'RuntimeException');
        $body = new CallableGraph('Box::VALUE:initializer', [], [new BasicBlock(0, [new BoundInstruction('error', 'constant', $source, 'error', constant: $error)], new Terminator(TerminatorKind::Throw, 'error'))], $source);
        $program = self::createStub(Program::class);
        $program->method('constant')->willReturn($body);
        $configuration = new DeriverConfig();
        $source1 = '<?php function target(){return 1;}';
        $budget = new Budget();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program2 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program2, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program2->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program1 = new Index($snapshotId1, $trees1);
        $contributions2 = new Contributions();
        $context = new Context($program, new ReturnQuery('target'), (new Context($program1, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators))->configuration, new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions2->evaluators, $contributions2->providers, $contributions2->domains, $contributions2->intrinsics, $contributions2->stateSlots), $configuration->standardEvaluators));
        $paths = (new ConstantTransfer(new Evaluator($context)))->initializer('Box::VALUE', new BoundInstruction('fetch', 'class-constant', $source, 'result'), new State(), new ClassConstant('Box', 'VALUE', type: 'int'));
        self::assertCount(1, $paths);
        self::assertSame(CompletionKind::Throw, $paths[0]->completion->kind);
        self::assertSame($error, $paths[0]->completion->value);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }
    #[DataProvider('providerFallbackConstants')]
    public function testInitializerUsesCapturedAndBuiltinFallbacksWithoutExecutingMissingBodies(string $symbol, ?ClassConstant $constant, Term $expected): void
    {
        $program = self::createStub(Program::class);
        $program->method('classes')->willReturn(['box' => new ClassDeclaration('Box', constants: ['VALUE' => Literal::constant('captured')])]);
        $configuration = new DeriverConfig();
        $source1 = '<?php function target(){return 1;}';
        $budget = new Budget();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program2 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program2, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program2->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program1 = new Index($snapshotId1, $trees1);
        $contributions2 = new Contributions();
        $context = new Context($program, new ReturnQuery('target'), (new Context($program1, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators))->configuration, new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions2->evaluators, $contributions2->providers, $contributions2->domains, $contributions2->intrinsics, $contributions2->stateSlots), $configuration->standardEvaluators));
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $state = new State();
        $paths = (new ConstantTransfer(new Evaluator($context)))->initializer($symbol, new BoundInstruction('fetch', 'constant-name', $source, 'result'), $state, $constant);
        self::assertSame([$state], $paths);
        self::assertSame($expected->kind->value, $state->registers['result']->kind->value);
        self::assertSame($expected->literal, $state->registers['result']->literal);
    }
    /**
     * @return iterable<string,array{string,ClassConstant|null,Term}>
     */
    public static function providerFallbackConstants(): iterable
    {
        yield 'integer width' => ['PHP_INT_SIZE', null, Term::constant(8)];
        yield 'class captured value' => ['Box::VALUE', new ClassConstant('Box', 'VALUE'), Term::constant('captured')];
        yield 'class missing value' => ['Box::Missing', new ClassConstant('Box', 'Missing'), Term::opaque('INCOMPLETE_SOURCE')];
    }
    /**
     * @param array<string,scalar|null> $attributes Class resolution syntax
     */
    #[DataProvider('providerDynamicMembers')]
    public function testMemberPreservesResolutionErrorsAndClassNames(Term $class, Term $name, array $attributes, string $completion, string $kind, int|string $literal): void
    {
        $source1 = '<?php class Box { public const VALUE=7; private const HIDDEN=9; } function target(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $state = new State();
        $state->registers['class'] = $class;
        $state->registers['name'] = $name;
        $instruction = new BoundInstruction('fetch', 'class-constant', $source, 'result', ['class', 'name'], attributes: $attributes);
        $paths = (new ConstantTransfer(new Evaluator($context)))->member(new CallableGraph('target', [], [], $source), $instruction, $state);
        self::assertCount(1, $paths);
        self::assertSame(CompletionKind::from($completion), $paths[0]->completion->kind);
        $value = $paths[0]->completion->value ?? $paths[0]->value('result');
        self::assertSame($kind, $value->kind->value);
        self::assertSame($literal, $value->literal);
    }
    /**
     * @return iterable<string,array{Term,Term,array<string,scalar|null>,string,string,int|string}>
     */
    public static function providerDynamicMembers(): iterable
    {
        yield 'ordinary' => [Term::constant('Box'), Term::constant('VALUE'), [], 'normal', 'constant', 7];
        yield 'runtime class name' => [Term::constant('bOx'), Term::constant('class'), ['literal-class' => false, 'class-name' => false], 'normal', 'constant', 'Box'];
        yield 'literal class spelling' => [Term::constant('bOx'), Term::constant('class'), ['literal-class' => true, 'class-name' => true], 'normal', 'constant', 'bOx'];
        yield 'missing literal class spelling' => [Term::constant('Missing'), Term::constant('class'), ['class-name' => true], 'normal', 'constant', 'Missing'];
        yield 'nonstring class' => [Term::constant(3), Term::constant('class'), ['literal-class' => false, 'class-name' => true], 'throw', 'throwable', 'TypeError'];
        yield 'symbolic class' => [Term::parameter('class'), Term::constant('VALUE'), [], 'normal', 'opaque', 'UNSUPPORTED_LANGUAGE_FEATURE'];
        yield 'symbolic name' => [Term::constant('Box'), Term::parameter('name'), [], 'normal', 'opaque', 'UNSUPPORTED_LANGUAGE_FEATURE'];
        yield 'missing class' => [Term::constant('Missing'), Term::constant('VALUE'), [], 'normal', 'opaque', 'INCOMPLETE_SOURCE'];
        yield 'missing member' => [Term::constant('Box'), Term::constant('MISSING'), [], 'throw', 'throwable', 'Error'];
        yield 'inaccessible member' => [Term::constant('Box'), Term::constant('HIDDEN'), [], 'throw', 'throwable', 'Error'];
    }
    public function testMemberResolvesTheClassNameAndMemberSelector(): void
    {
        $source1 = '<?php class Box {} function target(){}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input = new Sources([new Source('fixture.php', $source1)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input1 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input1, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input2 = new Sources([new Source('fixture.php', $source1)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source3): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source3->path), $source3->contents, $source3->declarationsOnly))), iterator_to_array($input2, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $source = new SourceRef('snapshot', 'constants.php', 4, 20);
        $state = new State();
        $state->registers['class'] = Term::constant('Box');
        $state->registers['name'] = Term::constant('class');
        $paths = (new ConstantTransfer(new Evaluator($context)))->member(new CallableGraph('target', [], [], $source), new BoundInstruction('fetch', 'class-constant', $source, 'result', ['class', 'name']), $state);
        self::assertSame('Box', $paths[0]->registers['result']->literal);
    }
    /**
     * @return array<string,array{string,string}> Captured source and independently checked JSON result
     */
    public static function providerProgramsClassNameProgramscases(): array
    {
        return ['literal missing class' => ['<?php function target(){return Missing::class;}', '"Missing"'], 'literal spelling' => ['<?php class Box{}function target(){return bOx::class;}', '"bOx"'], 'resolved namespace alias' => ['<?php namespace N{class Box{}}namespace{use N\Box as Alias;function target(){return Alias::class;}}', '"N\\\\Box"'], 'object class name' => ['<?php class Box{}function target(){$x=new Box;return $x::class;}', '"Box"'], 'canonical object class name' => ['<?php class Box{}function target(){$x=new bOx;return $x::class;}', '"Box"'], 'closure class name' => ['<?php function target(){$x=fn()=>1;return $x::class;}', '"Closure"'], 'enum class name' => ['<?php enum E{case A;}function target(){$x=E::A;return $x::class;}', '"E"'], 'native exception class name' => ['<?php function target(){$x=new RuntimeException;return $x::class;}', '"RuntimeException"'], 'string class name rejected' => ['<?php class Box{}function target(){$x="Box";try{return $x::class;}catch(TypeError $e){return "type";}}', '"type"'], 'array class name rejected' => ['<?php function target(){$x=[];try{return $x::class;}catch(TypeError $e){return "type";}}', '"type"'], 'integer class name rejected' => ['<?php function target(){$x=1;try{return $x::class;}catch(TypeError $e){return "type";}}', '"type"'], 'null class name rejected' => ['<?php function target(){$x=null;try{return $x::class;}catch(TypeError $e){return "type";}}', '"type"'], 'literal class dynamic name' => ['<?php class Box{}function target(){$name="class";return bOx::{$name};}', '"Box"'], 'string class dynamic name' => ['<?php class Box{}function target(){$class="bOx";$name="class";return $class::{$name};}', '"Box"'], 'object class dynamic name' => ['<?php class Box{}function target(){$class=new Box;$name="class";return $class::{$name};}', '"Box"'], 'object constant' => ['<?php class Box{const VALUE=3;}function target(){$x=new Box;return $x::VALUE;}', '3'], 'closure constant error' => ['<?php function target(){$x=fn()=>1;try{return $x::VALUE;}catch(Error $e){return "error";}}', '"error"'], 'unbound self closure' => ['<?php function target(){$f=fn()=>self::class;try{return $f();}catch(Error $e){return "error";}}', '"error"'], 'unbound parent closure' => ['<?php function target(){$f=fn()=>parent::class;try{return $f();}catch(Error $e){return "error";}}', '"error"'], 'unbound static closure' => ['<?php function target(){$f=fn()=>static::class;try{return $f();}catch(Error $e){return "error";}}', '"error"'], 'trait parent' => ['<?php trait T{function value(){return parent::class;}}class Base{}class Box extends Base{use T;}function target(){return (new Box)->value();}', '"Base"'], 'trait missing parent runtime' => ['<?php trait T{function value(){try{return parent::class;}catch(Error $e){return "error";}}}class Box{use T;}function target(){return (new Box)->value();}', '"error"'], 'late static class' => ['<?php class Base{static function value(){return [self::class,static::class];}}class Box extends Base{}function target(){return Box::value();}', '["Base","Box"]'], 'dynamic self constant' => ['<?php class Box{const VALUE=7;static function value(){$class="self";try{return $class::VALUE;}catch(Error $e){return "error";}}}function target(){return Box::value();}', '"error"'], 'dynamic parent constant' => ['<?php class Base{const VALUE=7;}class Box extends Base{static function value(){$class="parent";try{return $class::VALUE;}catch(Error $e){return "error";}}}function target(){return Box::value();}', '"error"'], 'dynamic static constant' => ['<?php class Box{const VALUE=7;static function value(){$class="static";try{return $class::VALUE;}catch(Error $e){return "error";}}}function target(){return Box::value();}', '"error"'], 'dynamic relative class keyword' => ['<?php class Box{static function value(){$class="self";$name="class";try{return $class::{$name};}catch(Error $e){return "error";}}}function target(){return Box::value();}', '"error"']];
    }
}
