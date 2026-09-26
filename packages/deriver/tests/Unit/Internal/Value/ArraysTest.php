<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Value;

use Deriver\Internal\Value\Arrays;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Arrays::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\AnalysisSession::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\Query::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ResultRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Api\Result\Alternative::class)]
#[UsesClass(\Deriver\Api\Result\Assessment::class)]
#[UsesClass(\Deriver\Api\Result\Derivation::class)]
#[UsesClass(\Deriver\Api\Result\DerivationResult::class)]
#[UsesClass(\Deriver\Api\Result\Statistics::class)]
#[UsesClass(\Deriver\Api\Result\StorageSnapshot::class)]
#[UsesClass(\Deriver\Internal\Api\QueryExecution::class)]
#[UsesClass(\Deriver\Internal\Api\QueryValidation::class)]
#[UsesClass(\Deriver\Internal\Api\ResultAssessment::class)]
#[UsesClass(\Deriver\Internal\Api\Session::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\DeclarationScanner::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ExpressionLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\GraphBuilder::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Lowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\ProjectIndex::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\LineMap::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\MagicContext::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Source\SyntaxSize::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\StatementLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Traits\Composition::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Program::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
#[UsesClass(\Deriver\Internal\Model\ModelBoundary::class)]
#[UsesClass(\Deriver\Internal\Model\ProviderInputs::class)]
#[UsesClass(\Deriver\Internal\Model\Registry::class)]
#[UsesClass(\Deriver\Internal\Model\StateRegistry::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\Dispatch::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Internal\Solver\Completion::class)]
#[UsesClass(\Deriver\Internal\Solver\Context::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Cell::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Components::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Key::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Table::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\CompletionRecord::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Invocation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arithmetic::class)]
#[UsesClass(Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\IntegerConversion::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
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
