<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Builtin;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Builtin\ArrayFunctions;
use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
use Deriver\Deriver\Evaluator\Builtin\TypePredicates;
use Deriver\Deriver\Evaluator\Call\ArgumentBindings;
use Deriver\Deriver\Evaluator\Call\BoundArgument;
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
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\Declaration\Parameter as DeclarationParameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\CallableCheck;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\CallResolution;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Inputs;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\NativeArguments;
use Deriver\Deriver\Evaluator\Execution\Call\ParameterBinding;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Modes;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Resolution;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Call\TypeCheck;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Assessment;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Completion\StorageSnapshot;
use Deriver\Deriver\Evaluator\Execution\Context;
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
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\IntrinsicTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Comparison;
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
use Deriver\Deriver\Lowerer\CallLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Parameter;
use Deriver\Deriver\Lowerer\Graph\Terminator;
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
#[CoversClass(ArrayFunctions::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(Argument::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(CallExecutor::class)]
#[UsesClass(CallResolution::class)]
#[UsesClass(CallableCheck::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(Inputs::class)]
#[UsesClass(NativeArguments::class)]
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
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(IntrinsicTransfer::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(ArgumentBindings::class)]
#[UsesClass(BoundArgument::class)]
#[UsesClass(FunctionEvaluator::class)]
#[UsesClass(Library::class)]
#[UsesClass(ScalarFunctions::class)]
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
#[UsesClass(CallLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(ExpressionLowering::class)]
#[UsesClass(InstructionEmitter::class)]
#[UsesClass(LoweringScope::class)]
#[UsesClass(StatementLowering::class)]
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
#[UsesClass(Comparison::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArrayFunctionsTest extends TestCase
{
    public function testApplyPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){return array_merge([2=>"left","x"=>1],[2=>"right","x"=>2]);}';
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
        self::assertSame([0 => 'left', 'x' => 2, 1 => 'right'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testCountIncludesNestedElementsOnlyInRecursiveMode(): void
    {
        $array = Term::fromNative(['a' => [1, [2]], 'b' => 3]);
        $functions = new ArrayFunctions();
        self::assertSame(2, $functions->count($array, false)->native());
        self::assertSame(5, $functions->count($array, true)->native());
    }
    public function testMembershipDistinguishesStrictAndLooseComparison(): void
    {
        $values = [Term::constant('2'), Term::fromNative([2])];
        $functions = new ArrayFunctions();
        self::assertSame(true, $functions->membership('in_array', $values)->native());
        self::assertSame(false, $functions->membership('in_array', [...$values, Term::constant(true)])->native());
    }
    public function testMembershipFindsKnownHeadEntriesOfAnUnknownMerge(): void
    {
        $merge = (new Arrays())->merge(Term::fromNative(['id', 'name']), Term::parameter('x', 'array'));
        $functions = new ArrayFunctions();
        self::assertSame(true, $functions->membership('in_array', [Term::constant('name'), $merge, Term::constant(true)])->native());
        self::assertSame(true, $functions->membership('array_key_exists', [Term::constant(1), $merge])->native());
        $arguments = [Term::constant('other'), $merge, Term::constant(true)];
        $absent = $functions->membership('in_array', $arguments);
        self::assertSame(TermKind::Intrinsic, $absent->kind);
        self::assertSame($arguments, $absent->operands);
        $key = Term::constant(2);
        self::assertSame([$key, $merge], $functions->membership('array_key_exists', [$key, $merge])->operands);
    }
    public function testKeyExistsDoesNotTrustEntriesThatUnknownCodeMayHaveRemoved(): void
    {
        $array = Term::array(['a' => new Term(TermKind::Opaque, 'UNKNOWN', attributes: ['maybeUninitialized' => true]), 'b' => Term::constant(1)], true);
        $functions = new ArrayFunctions();
        self::assertSame(TermKind::Intrinsic, $functions->keyExists(Term::constant('a'), $array)->kind);
        self::assertSame(true, $functions->keyExists(Term::constant('b'), $array)->native());
    }
    public function testKeysFiltersWithoutRenumberingSourceKeys(): void
    {
        $array = Term::fromNative(['first' => 2, 7 => '2', 'last' => 3]);
        $functions = new ArrayFunctions();
        self::assertSame(['first', 7], $functions->keys($array, [$array, Term::constant(2)])->native());
        self::assertSame(['first'], $functions->keys($array, [$array, Term::constant(2), Term::constant(true)])->native());
    }
    public function testKeyExistsIncludesNullValuesAndPreservesOpenRemainders(): void
    {
        $functions = new ArrayFunctions();
        self::assertSame(true, $functions->keyExists(Term::constant('a'), Term::fromNative(['a' => null]))->native());
        self::assertSame(false, $functions->keyExists(Term::constant('a'), Term::array([]))->native());
        self::assertSame(TermKind::Intrinsic, $functions->keyExists(Term::constant('a'), Term::array([], true))->kind);
    }
    /**
     * @param list<Term> $values \Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments after native binding
     */
    #[DataProvider('providerCountBoundaries')]
    public function testCountArgumentsPreservesInvalidModesAndUnknownProtocols(array $values, string $kind, mixed $literal): void
    {
        $result = (new ArrayFunctions())->countArguments($values);
        self::assertSame($kind, $result->kind->value);
        self::assertSame($literal, $result->literal);
    }
    /**
     * @return array<string,array{list<Term>,string,mixed}>
     */
    public static function providerCountBoundaries(): array
    {
        return ['unknown array normal' => [[Term::parameter('a', 'array')], 'intrinsic', 'count'], 'open array normal' => [[Term::array([], true)], 'intrinsic', 'count'], 'known recursive' => [[Term::fromNative([[1]]), Term::constant(1)], 'constant', 2], 'invalid known mode' => [[Term::array([]), Term::constant(2)], 'throwable', 'ValueError'], 'invalid unknown array mode' => [[Term::parameter('a', 'array'), Term::constant(-1)], 'throwable', 'ValueError'], 'unknown mode' => [[Term::array([]), Term::parameter('mode', 'int')], 'opaque', 'UNSUPPORTED_EVALUATOR_CASE'], 'unknown recursive array' => [[Term::parameter('a', 'array'), Term::constant(1)], 'opaque', 'UNSUPPORTED_EVALUATOR_CASE'], 'countable protocol' => [[Term::parameter('a', 'Countable')], 'opaque', 'UNSUPPORTED_EVALUATOR_CASE'], 'union protocol' => [[Term::parameter('a', 'array|Countable')], 'opaque', 'UNSUPPORTED_EVALUATOR_CASE']];
    }
    /**
     * @param list<Term> $values \Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments after native binding
     * @param mixed $expected Concrete result
     */
    #[DataProvider('providerArrayOperations')]
    public function testApplyPreservesKeyOrderAndComparisonModes(string $name, array $values, mixed $expected): void
    {
        self::assertSame($expected, (new ArrayFunctions())->apply($name, $values)->native());
    }
    /**
     * @return array<string,array{string,list<Term>,mixed}>
     */
    public static function providerArrayOperations(): array
    {
        return ['keys unfiltered' => ['array_keys', [Term::fromNative(['a' => null, 8 => false])], ['a', 8]], 'keys explicit null' => ['array_keys', [Term::fromNative(['a' => null, 8 => false]), Term::constant(null), Term::constant(true)], ['a']], 'keys loose false' => ['array_keys', [Term::fromNative(['a' => null, 8 => false]), Term::constant(false)], ['a', 8]], 'values renumber' => ['array_values', [Term::fromNative(['a' => null, 8 => false])], [null, false]], 'merge variadic' => ['array_merge', [Term::fromNative([[7 => 'a', 'x' => 1], [9 => 'b', 'x' => 2]])], [0 => 'a', 'x' => 2, 1 => 'b']], 'merge empty' => ['array_merge', [Term::array([])], []], 'count normal' => ['count', [Term::fromNative([[1, 2], 3])], 2], 'count recursive' => ['count', [Term::fromNative([[1, 2], 3]), Term::constant(1)], 4], 'contains loose' => ['in_array', [Term::constant('2'), Term::fromNative([1, 2, 3])], true], 'contains strict' => ['in_array', [Term::constant('2'), Term::fromNative([1, 2, 3]), Term::constant(true)], false], 'contains later exact' => ['in_array', [Term::constant(2), Term::array([Term::parameter('x'), Term::constant(2)])], true], 'contains absent' => ['in_array', [Term::constant(4), Term::fromNative([1, 2, 3])], false], 'key integer normalization' => ['array_key_exists', [Term::constant('2'), Term::fromNative([2 => null])], true], 'key leading zero' => ['array_key_exists', [Term::constant('02'), Term::fromNative([2 => null])], false], 'key null normalization' => ['array_key_exists', [Term::constant(null), Term::fromNative(['' => null])], true]];
    }
    /**
     * @param list<Term> $values \Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments after native binding
     */
    #[DataProvider('providerUnresolvedArrays')]
    public function testApplyRetainsUnresolvedArrayDependencies(string $name, array $values, string $kind, string $type): void
    {
        $result = (new ArrayFunctions())->apply($name, $values);
        self::assertSame($kind, $result->kind->value);
        self::assertSame($type, $result->attributes['type']);
        self::assertSame($values, $result->operands);
    }
    /**
     * @return array<string,array{string,list<Term>,string,string}>
     */
    public static function providerUnresolvedArrays(): array
    {
        return ['unknown values' => ['array_values', [Term::parameter('a', 'array')], 'intrinsic', 'array'], 'open keys' => ['array_keys', [Term::array([], true)], 'intrinsic', 'array'], 'unknown membership' => ['in_array', [Term::constant(1), Term::parameter('a', 'array')], 'intrinsic', 'bool'], 'open membership' => ['in_array', [Term::constant(1), Term::array([], true)], 'intrinsic', 'bool'], 'symbolic element' => ['in_array', [Term::constant(1), Term::array([Term::parameter('x')])], 'intrinsic', 'bool'], 'unknown strictness' => ['in_array', [Term::constant(1), Term::array([]), Term::parameter('strict', 'bool')], 'intrinsic', 'bool'], 'unknown key' => ['array_key_exists', [Term::parameter('key'), Term::array([])], 'intrinsic', 'bool'], 'unknown key filter' => ['array_keys', [Term::fromNative([1]), Term::parameter('filter')], 'opaque', 'array'], 'unknown keys strictness' => ['array_keys', [Term::array([]), Term::constant(1), Term::parameter('strict', 'bool')], 'opaque', 'array']];
    }
    public function testCountRetainsUnknownNestedShapes(): void
    {
        $functions = new ArrayFunctions();
        self::assertSame(TermKind::Opaque, $functions->count(Term::array([Term::array([], true)]), true)->kind);
        self::assertSame(TermKind::Opaque, $functions->count(Term::array([Term::array([Term::parameter('nested')])]), true)->kind);
        self::assertSame(1, $functions->count(Term::array([new Term(TermKind::Object, 'identity')]), true)->native());
    }
    public function testKeyExistsRejectsIllegalAggregateKeys(): void
    {
        $result = (new ArrayFunctions())->keyExists(Term::array([]), Term::array([]));
        self::assertSame(TermKind::Throwable, $result->kind);
        self::assertSame('TypeError', $result->literal);
    }
    public function testBoundaryKeySelectsTheFirstOrLastKeyOfClosedShapes(): void
    {
        $functions = new ArrayFunctions();
        $array = Term::fromNative(['a' => 1, 7 => 2]);
        self::assertSame('a', $functions->apply('array_key_first', [$array])->native());
        self::assertSame(7, $functions->apply('array_key_last', [$array])->native());
        self::assertNull($functions->boundaryKey('array_key_first', Term::array([]))->native());
        $open = Term::array([Term::constant(1)], true);
        self::assertSame(['intrinsic', 'int|string|null', [$open]], [$functions->boundaryKey('array_key_last', $open)->kind->value, $functions->boundaryKey('array_key_last', $open)->attributes['type'], $functions->boundaryKey('array_key_last', $open)->operands]);
    }
    public function testSliceRenumbersIntegerKeysUnlessPreserved(): void
    {
        $functions = new ArrayFunctions();
        $array = Term::fromNative([5 => 'a', 'k' => 'b', 9 => 'c']);
        self::assertSame(['k' => 'b', 0 => 'c'], $functions->apply('array_slice', [$array, Term::constant(1), Term::constant(null), Term::constant(false)])->native());
        self::assertSame([5 => 'a', 'k' => 'b'], $functions->slice([$array, Term::constant(0), Term::constant(2), Term::constant(true)])->native());
        self::assertSame([2], $functions->slice([Term::fromNative([1, 2, 3]), Term::constant(-2), Term::constant(-1), Term::constant(false)])->native());
        $unknown = [$array, Term::parameter('offset', 'int'), Term::constant(null), Term::constant(false)];
        self::assertSame(['intrinsic', 'array', $unknown], [$functions->slice($unknown)->kind->value, $functions->slice($unknown)->attributes['type'], $functions->slice($unknown)->operands]);
    }
}
