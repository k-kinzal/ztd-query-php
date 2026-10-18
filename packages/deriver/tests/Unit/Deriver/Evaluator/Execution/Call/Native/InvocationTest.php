<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Call\Native;

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
use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\Allocation;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Creation\Access;
use Deriver\Deriver\Evaluator\Execution\Call\Member\Invocation as MemberInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\MethodInvocation;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation;
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
use Deriver\Deriver\Evaluator\Scalar\Comparison;
use Deriver\Deriver\Evaluator\Scalar\Identity;
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

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation
 */
#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Invocation::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
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
#[UsesClass(Access::class)]
#[UsesClass(Builtins::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(HierarchyAccess::class)]
#[UsesClass(MemberInvocation::class)]
#[UsesClass(MethodInvocation::class)]
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
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(SummaryInvocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(CallableTransfer::class)]
#[UsesClass(ConstantTransfer::class)]
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
#[UsesClass(DispatchRequest::class)]
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
#[UsesClass(LineMap::class)]
#[UsesClass(MagicContext::class)]
#[UsesClass(AssignmentPatterns::class)]
#[UsesClass(ClassScope::class)]
#[UsesClass(TargetSyntax::class)]
#[UsesClass(Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(NumericString::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class InvocationTest extends TestCase
{
    public function testApplyChecksInternalConstructorTypes(): void
    {
        $source = '<?php function target(){try{new Exception(previous:new stdClass);}catch(TypeError $e){return "type";}return 999;}';
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
        self::assertSame('type', $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testCoercionsAppliesNullConversionOnlyInWeakNativeCalls(): void
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
        $invocation = new Invocation(new Evaluator(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators)));
        $source3 = '<?php function target(){return 1;}';
        $budget1 = new Budget();
        $configuration1 = new DeriverConfig();
        $contributions2 = new Contributions();
        $input3 = new Sources([new Source('fixture.php', $source3)]);
        $target1 = new TargetProfile($configuration1->phpVersion, $configuration1->integerBits, $configuration1->floatPrecision);
        $providers1 = new ProviderExpansion($input3, array_map(Term::fromNative(...), $configuration1->environment), $contributions2);
        $contributions3 = $providers1->contributions;
        $evaluators1 = new Evaluators(new TargetProfile($configuration1->phpVersion, $configuration1->integerBits, $configuration1->floatPrecision), new Registrations($contributions3->evaluators, $contributions3->providers, $contributions3->domains, $contributions3->intrinsics, $contributions3->stateSlots), $configuration1->standardEvaluators);
        $settings1 = new EvaluatorConfig($target1, $providers1->environment, $configuration1->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration1->evaluators));
        $identity1 = new SnapshotIdentity($providers1->input, $settings1, $evaluators1, $providers1->manifest);
        $snapshotId2 = $identity1->id;
        $input4 = $providers1->input;
        $parser2 = new Parser(new ParserConfig($target1->version));
        $trees2 = array_map(static fn (Source $source4): File => (new Resolver())->resolve($parser2->parse(new Source(Path::normalize($source4->path), $source4->contents, $source4->declarationsOnly))), iterator_to_array($input4, false));
        $program3 = new Index($snapshotId2, $trees2);
        $execution1 = new QueryExecution($program3, new ExecutionEvaluatorConfig($target1, $providers1->environment, $configuration1->standardEvaluators), $evaluators1, $identity1->snapshot(array_map(Frontier::fromDiagnostic(...), $program3->diagnostics())));
        $snapshotId3 = 'test';
        $input5 = new Sources([new Source('fixture.php', $source3)]);
        $profile1 = $execution1->configuration->target;
        $parser3 = new Parser(new ParserConfig($profile1->version));
        $trees3 = array_map(static fn (Source $source5): File => (new Resolver())->resolve($parser3->parse(new Source(Path::normalize($source5->path), $source5->contents, $source5->declarationsOnly))), iterator_to_array($input5, false));
        $program2 = new Index($snapshotId3, $trees3);
        $signature = (new Signatures((new Context($program2, new ReturnQuery('target', budget: $budget1), $execution1->configuration, $execution1->evaluators))->program))->graph('Exception', '__construct', new SourceRef('s', 'x.php', 0, 1));
        $actuals = [new PassedArgument(Term::constant(null), name: 'message')];
        self::assertSame('', $invocation->coercions($signature, $actuals, false)[0]->value->literal);
        self::assertNull($invocation->coercions($signature, $actuals, true)[0]->value->literal);
    }
    public function testInitializeRetainsInheritedNonzeroCodeWhenPassedZero(): void
    {
        $source = '<?php class X extends Exception{protected $message="old";protected $code=9;}function target(){$e=new X(code:0);return [$e->getMessage(),$e->getCode()];}';
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
        self::assertSame(['', 9], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
    }
    public function testAssignedPreservesPreviousAndNormalizesLineDefault(): void
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
        $invocation = new Invocation(new Evaluator(new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators)));
        $entry = new State();
        $entry->memory->write($entry->local('filename'), Term::constant('chosen.php'));
        self::assertNull($invocation->assigned('previous', Term::constant(null), $entry));
        self::assertSame(0, $invocation->assigned('line', Term::constant(null), $entry)?->literal);
        self::assertNull($invocation->assigned('code', Term::constant(0), $entry));
    }
    #[DataProvider('providerNativeCoercions')]
    public function testCoercionsUsesParameterPositionAndNameWithoutChangingReferenceMetadata(?string $name, int $position, Term $input, Term $expected): void
    {
        $source = '<?php function target(){return 1;}';
        $budget = new Budget();
        $configuration = new DeriverConfig();
        $contributions = new Contributions();
        $input1 = new Sources([new Source('fixture.php', $source)]);
        $target = new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision);
        $providers = new ProviderExpansion($input1, array_map(Term::fromNative(...), $configuration->environment), $contributions);
        $contributions1 = $providers->contributions;
        $evaluators = new Evaluators(new TargetProfile($configuration->phpVersion, $configuration->integerBits, $configuration->floatPrecision), new Registrations($contributions1->evaluators, $contributions1->providers, $contributions1->domains, $contributions1->intrinsics, $contributions1->stateSlots), $configuration->standardEvaluators);
        $settings = new EvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators, array_map((new MicroEvaluatorConversion())->convert(...), $configuration->evaluators));
        $identity = new SnapshotIdentity($providers->input, $settings, $evaluators, $providers->manifest);
        $snapshotId = $identity->id;
        $input2 = $providers->input;
        $parser = new Parser(new ParserConfig($target->version));
        $trees = array_map(static fn (Source $source1): File => (new Resolver())->resolve($parser->parse(new Source(Path::normalize($source1->path), $source1->contents, $source1->declarationsOnly))), iterator_to_array($input2, false));
        $program1 = new Index($snapshotId, $trees);
        $execution = new QueryExecution($program1, new ExecutionEvaluatorConfig($target, $providers->environment, $configuration->standardEvaluators), $evaluators, $identity->snapshot(array_map(Frontier::fromDiagnostic(...), $program1->diagnostics())));
        $snapshotId1 = 'test';
        $input3 = new Sources([new Source('fixture.php', $source)]);
        $profile = $execution->configuration->target;
        $parser1 = new Parser(new ParserConfig($profile->version));
        $trees1 = array_map(static fn (Source $source2): File => (new Resolver())->resolve($parser1->parse(new Source(Path::normalize($source2->path), $source2->contents, $source2->declarationsOnly))), iterator_to_array($input3, false));
        $program = new Index($snapshotId1, $trees1);
        $context = new Context($program, new ReturnQuery('target', budget: $budget), $execution->configuration, $execution->evaluators);
        $signature = (new Signatures($context->program))->graph('ErrorException', '__construct', new SourceRef('s', 'a.php', 0, 1));
        $location = new Location('argument', ['key']);
        $argument = new PassedArgument($input, $name, $location);
        $arguments = array_fill(0, $position, new PassedArgument(Term::constant(1)));
        $arguments[] = $argument;
        $result = (new Invocation(new Evaluator($context)))->coercions($signature, $arguments, false);
        self::assertSame($expected->kind->value, $result[$position]->value->kind->value);
        self::assertSame($expected->literal, $result[$position]->value->literal);
        self::assertSame($name, $result[$position]->name);
        self::assertSame($location, $result[$position]->location);
        self::assertCount($position + 1, $result);
    }
    /**
     * @return iterable<string,array{?string,int,Term,Term}>
     */
    public static function providerNativeCoercions(): iterable
    {
        yield 'positional message' => [null, 0, Term::constant(null), Term::constant('')];
        yield 'positional code' => [null, 1, Term::constant(null), Term::constant(0)];
        yield 'positional severity' => [null, 2, Term::constant(null), Term::constant(0)];
        yield 'nullable filename' => [null, 3, Term::constant(null), Term::constant(null)];
        yield 'nullable line' => [null, 4, Term::constant(null), Term::constant(null)];
        yield 'named message' => ['message', 0, Term::constant(null), Term::constant('')];
        yield 'named code' => ['code', 0, Term::constant(null), Term::constant(0)];
        yield 'unknown parameter' => ['missing', 0, Term::constant(null), Term::constant(null)];
        yield 'extra positional' => [null, 6, Term::constant(null), Term::constant(null)];
        yield 'false unchanged' => ['message', 0, Term::constant(false), Term::constant(false)];
        yield 'symbolic unchanged' => ['message', 0, Term::parameter('x'), Term::parameter('x')];
    }
    #[DataProvider('providerAssignedFields')]
    public function testAssignedFollowsNativeFieldRetentionAndFilenameRules(string $name, Term $value, Term $filename, ?Term $expected): void
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
        $entry = new State();
        $entry->memory->write($entry->local('filename'), $filename);
        $result = (new Invocation(new Evaluator($context)))->assigned($name, $value, $entry);
        self::assertEquals($expected, $result);
    }
    /**
     * @return iterable<string,array{string,Term,Term,?Term}>
     */
    public static function providerAssignedFields(): iterable
    {
        $null = Term::constant(null);
        yield 'zero code' => ['code', Term::constant(0), $null, null];
        yield 'nonzero code' => ['code', Term::constant(7), $null, Term::constant(7)];
        yield 'symbolic code' => ['code', Term::parameter('n', 'int'), $null, Term::parameter('n', 'int')];
        yield 'null previous' => ['previous', $null, $null, null];
        yield 'null filename' => ['filename', $null, $null, null];
        yield 'null line no file' => ['line', $null, $null, null];
        yield 'null line chosen file' => ['line', $null, Term::constant('chosen.php'), Term::constant(0)];
        yield 'null line unknown file' => ['line', $null, Term::parameter('file', 'string|null'), Term::opaque('RUNTIME_STACK', 'int')];
        yield 'explicit line' => ['line', Term::constant(42), $null, Term::constant(42)];
        yield 'empty message' => ['message', Term::constant(''), $null, Term::constant('')];
    }
    public function testApplyDeclinesUnknownNativeClassesAndMethods(): void
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
        $invocation = new Invocation(new Evaluator($context));
        $instruction = new BoundInstruction('i', 'method', new SourceRef('s', 'a.php', 0, 1), 'result');
        $receiver = new Term(TermKind::Object, 'e', attributes: ['class' => 'Exception']);
        self::assertNull($invocation->apply('UnknownClass', 'run', [], new State(), $instruction, null, false));
        self::assertNull($invocation->apply('Exception', 'missing', [], new State(), $instruction, $receiver, false));
        self::assertSame([], $context->frontiers);
    }
    #[DataProvider('providerInvalidNativeReceivers')]
    public function testApplyRejectsCloningAndIncompatibleReceivers(string $method, ?Term $receiver): void
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
        $instruction = new BoundInstruction('i', 'method', new SourceRef('s', 'a.php', 0, 1), 'result');
        $paths = (new Invocation(new Evaluator($context)))->apply('Exception', $method, [], new State(), $instruction, $receiver, false);
        self::assertNotNull($paths);
        self::assertCount(1, $paths);
        self::assertSame(CompletionKind::Throw, $paths[0]->completion->kind);
        self::assertSame('Error', $paths[0]->completion->value?->literal);
        self::assertArrayNotHasKey('result', $paths[0]->registers);
    }
    /**
     * @return iterable<string,array{string,?Term}>
     */
    public static function providerInvalidNativeReceivers(): iterable
    {
        yield 'clone' => ['__clone', new Term(TermKind::Object, 'e', attributes: ['class' => 'Exception'])];
        yield 'missing receiver' => ['getMessage', null];
        yield 'wrong class' => ['getMessage', new Term(TermKind::Object, 'e', attributes: ['class' => 'stdClass'])];
    }
    #[DataProvider('providerNativeResidualMethods')]
    public function testApplyKeepsRuntimeStackAndSerializationMethodsBehindABoundary(string $method): void
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
        $instruction = new BoundInstruction('i', 'method', new SourceRef('s', 'a.php', 0, 1), 'result');
        $receiver = new Term(TermKind::Object, 'e', attributes: ['class' => 'Exception']);
        $paths = (new Invocation(new Evaluator($context)))->apply('Exception', $method, [], new State(), $instruction, $receiver, false);
        self::assertNotNull($paths);
        self::assertSame(['normal', 'throw'], array_map(static fn (State $path): string => $path->completion->kind->value, $paths));
        self::assertSame('MISSING_CALL_MODEL', array_values($context->frontiers)[0]->code);
    }
    /**
     * @return iterable<string,array{string}>
     */
    public static function providerNativeResidualMethods(): iterable
    {
        yield 'trace' => ['getTrace'];
        yield 'trace string' => ['getTraceAsString'];
        yield 'string conversion' => ['__toString'];
        yield 'wake up' => ['__wakeup'];
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProvider('providerNativePrograms')]
    public function testApplyPreservesOracleCheckedNativeConstructorsAndGetters(string $source, string $expected): void
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
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame(json_decode($expected, true, flags: JSON_THROW_ON_ERROR), $result->normalOutcomes[0]->values['return']->native());
    }
    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerNativePrograms(): iterable
    {
        return ['native:messageCode' => ['<?php function target(){$e=new Exception("hello",7);return [$e->getMessage(),$e->getCode(),$e->getPrevious()];}', '["hello",7,null]'], 'native:named' => ['<?php function target(){$e=new RuntimeException(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}', '["hello",7]'], 'native:inherited' => ['<?php class X extends Exception{}function target(){$e=new X(code:7,message:"hello");return [$e->getMessage(),$e->getCode()];}', '["hello",7]'], 'native:codeZero' => ['<?php class X extends Exception{protected $message="x";protected $code=9;}function target(){$a=new X;$b=new X(code:0);$c=new X(message:"a");return [$a->getMessage(),$a->getCode(),$b->getMessage(),$b->getCode(),$c->getMessage(),$c->getCode()];}', '["x",9,"",9,"a",9]'], 'native:previous' => ['<?php function target(){$p=new Exception("first");$e=new Exception(previous:$p);$previous=$e->getPrevious();$equal=$previous===$p;return [$equal,$previous->getMessage()];}', '[true,"first"]'], 'native:parent' => ['<?php class X extends Exception{function __construct(){parent::__construct("a",2);}}function target(){$e=new X;return [$e->getMessage(),$e->getCode()];}', '["a",2]'], 'native:clone' => ['<?php function target(){try{$x=clone new Exception;}catch(Error $e){return "error";}return 999;}', '"error"'], 'native:arrayMessage' => ['<?php function target(){try{new Exception([]);}catch(TypeError $e){return "type";}return 999;}', '"type"'], 'native:badCode' => ['<?php function target(){try{new Exception(code:"no");}catch(TypeError $e){return "type";}return 999;}', '"type"'], 'native:badPrevious' => ['<?php function target(){try{new Exception(previous:new stdClass);}catch(TypeError $e){return "type";}return 999;}', '"type"'], 'native:unknownNamed' => ['<?php function target(){try{new Exception(other:1);}catch(Error $e){return "name";}return 999;}', '"name"'], 'native:extra' => ['<?php function target(){try{new Exception("",0,null,1);}catch(ArgumentCountError $e){return "count";}return 999;}', '"count"'], 'native:strict' => ['<?php declare(strict_types=1);function target(){try{new Exception(1);}catch(TypeError $e){return "type";}return 999;}', '"type"'], 'native:weak' => ['<?php function target(){$e=new Exception(1,"2");return [$e->getMessage(),$e->getCode()];}', '["1",2]'], 'native:errorDefaults' => ['<?php class X extends ErrorException{protected $message="x";protected $code=9;protected int $severity=2;}function target(){$x=new X;return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}', '["x",9,1]'], 'native:repeatPrevious' => ['<?php class X extends Exception{function clear(){parent::__construct();}function reset(){parent::__construct(previous:null);}}function target(){$p=new Exception;$x=new X("x",9,$p);$x->clear();$previous=$x->getPrevious();$a=[$x->getMessage(),$x->getCode(),$previous===$p];$x->reset();$previous=$x->getPrevious();return [$a,$x->getMessage(),$x->getCode(),$previous===$p];}', '[["x",9,true],"",9,true]'], 'native:severity' => ['<?php function target(){$x=new ErrorException(severity:8);return [$x->getMessage(),$x->getCode(),$x->getSeverity()];}', '["",0,8]'], 'native:getterArity' => ['<?php function target(){try{(new Exception)->getMessage(1);}catch(ArgumentCountError $e){return "count";}return 999;}', '"count"'], 'native:protected' => ['<?php function target(){try{return (new Exception("a"))->message;}catch(Error $e){return "access";}}', '"access"'], 'native:sourceProperty' => ['<?php class X extends Exception{function change(){$this->message=42;$this->code="custom";}}function target(){$e=new X;$e->change();return [$e->getMessage(),$e->getCode()];}', '["42","custom"]'], 'native:caseInsensitive' => ['<?php function target(){try{throw new runtimeexception;}catch(EXCEPTION $e){return "caught";}}', '"caught"'], 'native:errorHierarchy' => ['<?php function target(){$e=new DivisionByZeroError("zero");return [$e instanceof ArithmeticError,$e instanceof Throwable,$e->getMessage()];}', '[true,true,"zero"]'], 'native:missingMethod' => ['<?php function target(){try{(new Exception)->absent();}catch(Error $e){return "missing";}return 999;}', '"missing"'], 'native:filename' => ['<?php function target(){$e=new ErrorException(filename:"chosen.php");return [$e->getFile(),$e->getLine()];}', '["chosen.php",0]'], 'native:line' => ['<?php function target(){$e=new ErrorException(filename:"chosen.php",line:42);return [$e->getFile(),$e->getLine()];}', '["chosen.php",42]']];
    }
}
