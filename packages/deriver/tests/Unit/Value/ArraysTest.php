<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\ControlFlow\BasicBlock;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Terminator;
use Deriver\Evaluation\Call\ArgumentBinding;
use Deriver\Evaluation\Call\ArgumentOrder;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Call\TypeCheck;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Cell;
use Deriver\Evaluation\Demand\Components;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\Demand\Table;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\Offset\Path;
use Deriver\Evaluation\Offset\Transfer;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\CompletionRecord;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Invocation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Memory\StorageCapture;
use Deriver\Model\Registration\Extensions;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\StateRegistry;
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
use Deriver\Source\Compilation\AggregateLowering;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\Control\DestructuringLowering;
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
use Deriver\Value\Arithmetic;
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arrays::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
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
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Cell::class)]
#[UsesClass(Components::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Key::class)]
#[UsesClass(Table::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
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
#[UsesClass(ProviderInputs::class)]
#[UsesClass(Registry::class)]
#[UsesClass(StateRegistry::class)]
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
#[UsesClass(AggregateLowering::class)]
#[UsesClass(AssignmentLowering::class)]
#[UsesClass(CallableCompiler::class)]
#[UsesClass(DestructuringLowering::class)]
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
#[UsesClass(Arithmetic::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArraysTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testNextPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$a=[-5=>"first"];$a[]="next";return $a;}');
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
        $removed = new Term('array', operands:[], attributes:['open' => false,'next' => PHP_INT_MAX]);
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
        self::assertSame('array-merge', $merged->kind);
        self::assertSame([$left,$right], $merged->operands);
        self::assertSame('array', $merged->attributes['type']);
        $updated = $arrays->set($merged, null, Term::constant(7));
        self::assertSame('array-set', $updated->kind);
        self::assertSame($merged, $updated->operands[0]);
        self::assertSame('append', $updated->operands[1]->kind);
        self::assertSame(7, $updated->operands[2]->literal);
    }

    public function testMergeRetainsEitherOpenRemainderAndUnknownDestination(): void
    {
        $open = Term::array(['a' => Term::constant(1)], true);
        $closed = Term::fromNative(['a' => 2]);
        $arrays = new Arrays();
        self::assertSame('array-merge', $arrays->merge($open, $closed)->kind);
        self::assertSame('array-merge', $arrays->merge($closed, $open)->kind);
        self::assertSame('array-merge', $arrays->merge(Term::parameter('a', 'array'), $closed)->kind);
    }

    public function testSetRetainsExplicitKeysAfterAnUnknownMerge(): void
    {
        $array = Term::parameter('a', 'array');
        $key = Term::constant('known');
        $value = Term::constant(4);
        $result = (new Arrays())->set($array, $key, $value);
        self::assertSame([$array,$key,$value], $result->operands);
        self::assertSame('array', $result->attributes['type']);
    }


    public function testNextUsesTheLargestIntegerKeyAndZeroForOnlyNamedKeys(): void
    {
        $arrays = new Arrays();
        self::assertSame(0, $arrays->next(new Term('array')));
        self::assertSame(0, $arrays->next(Term::fromNative(['named' => 1])));
        self::assertSame(11, $arrays->next(Term::fromNative([10 => 1,2 => 2,-3 => 3])));
        self::assertSame(-1, $arrays->next(Term::fromNative([-2 => 1,-5 => 2])));
    }

    public function testSetRetainsEarlierAppendHistoryAndOpenRemainders(): void
    {
        $arrays = new Arrays();
        $history = new Term('array', operands:[], attributes:['next' => 9]);
        $result = $arrays->set($history, Term::constant(2), Term::constant('new'));
        self::assertSame(9, $arrays->next($result));
        self::assertSame([2 => 'new'], $result->native());
        $open = $arrays->set(Term::array(['a' => Term::constant(1)], true), Term::constant('b'), Term::constant(2));
        self::assertTrue($open->attributes['open']);
        self::assertNull($arrays->next($open));
        self::assertSame(['a','b'], array_keys($open->operands));
    }

    public function testSetUnknownKeysKeepKnownPresenceWithoutKeepingStaleValues(): void
    {
        $previous = Term::constant(1);
        $next = Term::constant(2);
        $array = Term::array(['a' => $previous]);
        $result = (new Arrays())->set($array, Term::parameter('key', 'string'), $next);
        self::assertTrue($result->attributes['open']);
        self::assertSame(['a'], array_keys($result->operands));
        self::assertSame('opaque', $result->operands['a']->kind);
        self::assertSame($previous, $result->operands['a']->operands[0]);
        self::assertSame($next, $result->operands['a']->operands[1]);
        self::assertSame('array-key', $result->operands['a']->operands[2]->kind);
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
        $result = (new Arrays())->merge(new Term('array', operands:['a' => Term::constant(1)]), new Term('array', operands:[Term::constant(2)]));
        self::assertSame(['a' => 1,0 => 2], $result->native());
    }

    public function testMergeStopsAtTheFirstAppendFailure(): void
    {
        $result = (new Arrays())->merge(Term::fromNative([PHP_INT_MAX => 1]), Term::fromNative([2,3]));
        self::assertSame('throwable', $result->kind);
        self::assertSame('Error', $result->literal);
    }

    public function testSetPreservesSecretKeysAndUnknownValuesInEmptyShapes(): void
    {
        $arrays = new Arrays();
        self::assertTrue($arrays->set(Term::array([]), Term::constant('secret-key', true), Term::constant(1))->isSecret());
        self::assertTrue($arrays->set(Term::fromNative([], true), Term::constant('public'), Term::constant(1))->isSecret());
        self::assertTrue($arrays->set(Term::array([]), Term::parameter('unknown'), Term::constant(1, true))->isSecret());
        self::assertTrue($arrays->set(Term::array([]), new Term('parameter', 'unknown', secret:true), Term::constant(1))->isSecret());
        self::assertTrue($arrays->merge(Term::array([]), Term::fromNative([], true))->isSecret());
    }
}
