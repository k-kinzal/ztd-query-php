<?php

declare(strict_types=1);

namespace Tests\Unit\Value;

use Deriver\Value\Arrays;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arrays::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\ControlFlow\Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Cell::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Components::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Key::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Table::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Invocation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\ProviderInputs::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Derivation::class)]
#[UsesClass(\Deriver\Result\DerivationResult::class)]
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
#[UsesClass(\Deriver\Source\Compilation\AggregateLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\AssignmentLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\CallableCompiler::class)]
#[UsesClass(\Deriver\Source\Compilation\Control\DestructuringLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\ExpressionLowering::class)]
#[UsesClass(\Deriver\Source\Compilation\GraphBuilder::class)]
#[UsesClass(\Deriver\Source\Compilation\Lowering::class)]
#[UsesClass(\Deriver\Source\Compilation\StatementLowering::class)]
#[UsesClass(\Deriver\Source\Declaration\CallableSource::class)]
#[UsesClass(\Deriver\Source\Declaration\DeclarationScanner::class)]
#[UsesClass(\Deriver\Source\Declaration\ProjectIndex::class)]
#[UsesClass(\Deriver\Source\Declaration\Traits\Composition::class)]
#[UsesClass(\Deriver\Source\LineMap::class)]
#[UsesClass(\Deriver\Source\MagicContext::class)]
#[UsesClass(\Deriver\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Source\Validation\AssignmentPatterns::class)]
#[UsesClass(\Deriver\Source\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Source\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Value\Arithmetic::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
