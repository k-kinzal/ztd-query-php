<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Scalar;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Call\StateRegistry;
use Deriver\Deriver\Evaluator\EvaluatorConfig;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentBinding;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
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
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Offset\Address;
use Deriver\Deriver\Evaluator\Execution\Offset\Path;
use Deriver\Deriver\Evaluator\Execution\Offset\Transfer;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\CompletionRecord;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Invocation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
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
use Deriver\Deriver\Lowerer\Control\DestructuringLowering;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\CallableSource;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Lowerer\Declaration\Indexer;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\ExpressionLowering;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
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
use Deriver\Deriver\Sources\Path as SourcesPath;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\ParserException;
use Deriver\Exception\ResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Arrays::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Deriver::class)]
#[UsesClass(BasicBlock::class)]
#[UsesClass(CallableGraph::class)]
#[UsesClass(CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(Terminator::class)]
#[UsesClass(ArgumentBinding::class)]
#[UsesClass(ArgumentOrder::class)]
#[UsesClass(Dispatch::class)]
#[UsesClass(TypeBinding::class)]
#[UsesClass(TypeCheck::class)]
#[UsesClass(Completion::class)]
#[UsesClass(Context::class)]
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
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Transfer::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(CompletionRecord::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Invocation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(Location::class)]
#[UsesClass(Materialization::class)]
#[UsesClass(Memory::class)]
#[UsesClass(ReferenceConstraint::class)]
#[UsesClass(StorageCapture::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(ProviderExpansion::class)]
#[UsesClass(Evaluators::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(QueryEncoding::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(Statistics::class)]
#[UsesClass(StorageSnapshot::class)]
#[UsesClass(Parser::class)]
#[UsesClass(File::class)]
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(Lowerer::class)]
#[UsesClass(DestructuringLowering::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArraysTest extends TestCase
{
    public function testNextPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){$a=[-5=>"first"];$a[]="next";return $a;}';
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
        self::assertSame([-5 => 'first', -4 => 'next'], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testNextUsesPhp83NegativeAppendRules(): void
    {
        self::assertSame(-4, (new Arrays())->next(Term::fromNative([-5 => 1])));
    }
    public function testSetNormalizesIntegerKeys(): void
    {
        $result = (new Arrays())->set(Term::array([]), Term::constant('2'), Term::constant('value'));
        self::assertSame([2 => 'value'], $result->native());
    }
    public function testMergeReindexesNumericKeys(): void
    {
        $result = (new Arrays())->merge(Term::fromNative([5 => 'a', 'x' => 1]), Term::fromNative([9 => 'b', 'x' => 2]));
        self::assertSame([5 => 'a', 'x' => 2, 6 => 'b'], $result->native());
    }
    public function testAppendKeyKeepsTheMaximumCounterReusableAfterUnset(): void
    {
        $arrays = new Arrays();
        $occupied = Term::array([PHP_INT_MAX => Term::constant(1)]);
        self::assertSame('Error', $arrays->appendKey($occupied)->literal);
        $removed = new Term(TermKind::Array, operands: [], attributes: ['open' => false, 'next' => PHP_INT_MAX]);
        self::assertSame(PHP_INT_MAX, $arrays->appendKey($removed)->literal);
        self::assertSame([PHP_INT_MAX => 2], $arrays->set($removed, null, Term::constant(2))->native());
    }
    public function testSetRejectsIllegalKeysAndRetainsUnknownAppendPosition(): void
    {
        $arrays = new Arrays();
        self::assertSame('TypeError', $arrays->set(Term::array([]), Term::array([]), Term::constant(1))->literal);
        $open = $arrays->set(Term::array([], true), null, Term::constant(1));
        self::assertSame([], $open->operands);
        self::assertTrue($open->attributes['open']);
    }
    public function testMergeRetainsTheUnknownOverwriteAndFollowingAppend(): void
    {
        $left = Term::fromNative(['a' => 1]);
        $right = Term::parameter('values', 'array');
        $arrays = new Arrays();
        $merged = $arrays->merge($left, $right);
        self::assertSame(TermKind::ArrayMerge, $merged->kind);
        self::assertSame([$left, $right], $merged->operands);
        self::assertSame('array', $merged->attributes['type']);
        $updated = $arrays->set($merged, null, Term::constant(7));
        self::assertSame(TermKind::ArraySet, $updated->kind);
        self::assertSame($merged, $updated->operands[0]);
        self::assertSame(TermKind::Append, $updated->operands[1]->kind);
        self::assertSame(7, $updated->operands[2]->literal);
    }
    public function testMergeRetainsEitherOpenRemainderAndUnknownDestination(): void
    {
        $open = Term::array(['a' => Term::constant(1)], true);
        $closed = Term::fromNative(['a' => 2]);
        $arrays = new Arrays();
        self::assertSame(TermKind::ArrayMerge, $arrays->merge($open, $closed)->kind);
        self::assertSame(TermKind::ArrayMerge, $arrays->merge($closed, $open)->kind);
        self::assertSame(TermKind::ArrayMerge, $arrays->merge(Term::parameter('a', 'array'), $closed)->kind);
    }
    public function testHeadKeepsOnlyClosedIntegerKeyedDestinationsOfMerges(): void
    {
        $arrays = new Arrays();
        $head = Term::fromNative(['id', 'name']);
        $unknown = Term::parameter('x', 'array');
        $merge = $arrays->merge($head, $unknown);
        self::assertSame($head, $arrays->head($merge));
        self::assertSame($head, $arrays->head($arrays->merge($merge, Term::parameter('y', 'array'))));
        self::assertNull($arrays->head($head));
        self::assertNull($arrays->head($arrays->merge(Term::fromNative(['k' => 'id']), $unknown)));
        self::assertNull($arrays->head($arrays->merge(Term::array([]), $unknown)));
        self::assertNull($arrays->head($arrays->merge(Term::array([Term::constant('id')], true), $unknown)));
    }
    public function testSplitSeparatesOnlyASingleMergeLevel(): void
    {
        $arrays = new Arrays();
        $head = Term::fromNative(['id']);
        $unknown = Term::parameter('x', 'array');
        $merge = $arrays->merge($head, $unknown);
        self::assertSame([$head, $unknown], $arrays->split($merge));
        self::assertNull($arrays->split($arrays->merge($merge, Term::parameter('y', 'array'))));
    }
    public function testAppendableRequiresRoomForEveryMergedSource(): void
    {
        $arrays = new Arrays();
        $unknown = Term::parameter('x', 'array');
        self::assertTrue($arrays->appendable(Term::fromNative(['k' => 1, 5 => 2])));
        self::assertTrue($arrays->appendable($arrays->merge(Term::fromNative([1]), $unknown)));
        self::assertTrue($arrays->appendable(Term::array([PHP_INT_MAX - 4294967297 => Term::constant(1)])));
        self::assertFalse($arrays->appendable(Term::array([PHP_INT_MAX - 4294967296 => Term::constant(1)])));
        self::assertFalse($arrays->appendable($arrays->merge(Term::array([PHP_INT_MAX - 4294967297 => Term::constant(1)]), $unknown)));
        self::assertFalse($arrays->appendable(Term::array([], true)));
        self::assertFalse($arrays->appendable($unknown));
    }
    public function testSetRetainsExplicitKeysAfterAnUnknownMerge(): void
    {
        $array = Term::parameter('a', 'array');
        $key = Term::constant('known');
        $value = Term::constant(4);
        $result = (new Arrays())->set($array, $key, $value);
        self::assertSame([$array, $key, $value], $result->operands);
        self::assertSame('array', $result->attributes['type']);
    }
    public function testNextUsesTheLargestIntegerKeyAndZeroForOnlyNamedKeys(): void
    {
        $arrays = new Arrays();
        self::assertSame(0, $arrays->next(new Term(TermKind::Array)));
        self::assertSame(0, $arrays->next(Term::fromNative(['named' => 1])));
        self::assertSame(11, $arrays->next(Term::fromNative([10 => 1, 2 => 2, -3 => 3])));
        self::assertSame(-1, $arrays->next(Term::fromNative([-2 => 1, -5 => 2])));
    }
    public function testSetRetainsEarlierAppendHistoryAndOpenRemainders(): void
    {
        $arrays = new Arrays();
        $history = new Term(TermKind::Array, operands: [], attributes: ['next' => 9]);
        $result = $arrays->set($history, Term::constant(2), Term::constant('new'));
        self::assertSame(9, $arrays->next($result));
        self::assertSame([2 => 'new'], $result->native());
        $open = $arrays->set(Term::array(['a' => Term::constant(1)], true), Term::constant('b'), Term::constant(2));
        self::assertTrue($open->attributes['open']);
        self::assertNull($arrays->next($open));
        self::assertSame(['a', 'b'], array_keys($open->operands));
    }
    public function testSetUnknownKeysKeepKnownPresenceWithoutKeepingStaleValues(): void
    {
        $previous = Term::constant(1);
        $next = Term::constant(2);
        $array = Term::array(['a' => $previous]);
        $result = (new Arrays())->set($array, Term::parameter('key', 'string'), $next);
        self::assertTrue($result->attributes['open']);
        self::assertSame(['a'], array_keys($result->operands));
        self::assertSame(TermKind::Opaque, $result->operands['a']->kind);
        self::assertSame($previous, $result->operands['a']->operands[0]);
        self::assertSame($next, $result->operands['a']->operands[1]);
        self::assertSame(TermKind::ArrayKey, $result->operands['a']->operands[2]->kind);
    }
    public function testAppendKeyKeepsItsUnknownSourceDependency(): void
    {
        $array = Term::array([], true);
        $result = (new Arrays())->appendKey($array);
        self::assertSame('UNKNOWN_APPEND_INDEX', $result->literal);
        self::assertSame('int', $result->attributes['type']);
        self::assertSame([$array], $result->operands);
    }
    public function testMergeTreatsShapesWithoutRemainderMetadataAsClosed(): void
    {
        $result = (new Arrays())->merge(new Term(TermKind::Array, operands: ['a' => Term::constant(1)]), new Term(TermKind::Array, operands: [Term::constant(2)]));
        self::assertSame(['a' => 1, 0 => 2], $result->native());
    }
    public function testMergeStopsAtTheFirstAppendFailure(): void
    {
        $result = (new Arrays())->merge(Term::fromNative([PHP_INT_MAX => 1]), Term::fromNative([2, 3]));
        self::assertSame(TermKind::Throwable, $result->kind);
        self::assertSame('Error', $result->literal);
    }
    public function testTailKeepsOnlyAppendSuffixesWithAvailableIndices(): void
    {
        $arrays = new Arrays();
        $prefix = $arrays->merge(Term::array([]), Term::parameter('x', 'array'));
        $array = $arrays->set($arrays->set($prefix, null, Term::constant('a')), null, Term::constant('z'));
        $tail = $arrays->tail($array);
        self::assertNotNull($tail);
        self::assertSame($prefix, $tail[0]);
        self::assertSame(['a', 'z'], $tail[1]->native());
        self::assertNull($arrays->tail($arrays->set($prefix, Term::constant('key'), Term::constant('z'))));
    }
}
