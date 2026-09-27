<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Control;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Solver\Control\IterationStep::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Api\Execution\ResourceLimits::class)]
#[UsesClass(\Deriver\Api\Execution\SourceLimits::class)]
#[UsesClass(\Deriver\Api\Project\Configuration::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Api\Project\ProjectSnapshot::class)]
#[UsesClass(\Deriver\Api\Project\SourceFile::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
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
#[UsesClass(\Deriver\Internal\Constraint\Constraints::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AggregateLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\AssignmentLowering::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\GraphTemplate::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SnapshotRebase::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Cache\SyntaxTree::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableCompiler::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\CallableSource::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Control\LoopLowering::class)]
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
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\ClassScope::class)]
#[UsesClass(\Deriver\Internal\Frontend\Php\Validation\TargetSyntax::class)]
#[UsesClass(\Deriver\Internal\IR\BasicBlock::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIR::class)]
#[UsesClass(\Deriver\Internal\IR\CallableIdentity::class)]
#[UsesClass(\Deriver\Internal\IR\Instruction::class)]
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\LiveArray::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Internal\Model\Extensions::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Control\IteratorCursor::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Resources::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\StateJoin::class)]
#[UsesClass(\Deriver\Internal\Solver\Control\Unwinding::class)]
#[UsesClass(\Deriver\Internal\Solver\Demand\Discovery::class)]
#[UsesClass(\Deriver\Internal\Solver\Dependencies::class)]
#[UsesClass(\Deriver\Internal\Solver\InstructionTransfer::class)]
#[UsesClass(\Deriver\Internal\Solver\Machine::class)]
#[UsesClass(\Deriver\Internal\Solver\Model\SlotReference::class)]
#[UsesClass(\Deriver\Internal\Solver\ObservationCollector::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\Conversions::class)]
#[UsesClass(\Deriver\Internal\Solver\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Internal\Solver\State::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Internal\Solver\Summary\Isolation::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Internal\Solver\Transfer\ReferenceAssignment::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class IterationStepTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testEvaluatePreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){ $a=[1,2,3];foreach($a as &$x){}$x=4;unset($x);$x=5;return $a;}');
        self::assertSame([1, 2, 4], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testInitializePinsTheArrayCellForLiveTraversal(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->addresses['array'] = $state->memory->allocate(\Deriver\Value\Term::fromNative([1,2]));
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'iterator', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'it', ['array'], attributes:['byReference' => true]);
        $iterator = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->initialize($instruction, $state);
        self::assertCount(1, $state->memory->liveArrays);
        self::assertSame('iterator', $iterator->kind);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCursorReads')]
    public function testEvaluateReadsCursorValuesAndKeysWithContainerConfidentiality(string $operation, bool $secret, mixed $expected): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['it'] = new \Deriver\Value\Term('iterator', 'cursor');
        $array = new \Deriver\Value\Term('array', operands:['first' => \Deriver\Value\Term::constant(10),'second' => \Deriver\Value\Term::constant(20)], attributes:['open' => false], secret:$secret);
        $state->iterators['cursor'] = new \Deriver\Internal\Solver\Control\IteratorCursor($array, position:1);
        $instruction = new \Deriver\Internal\IR\Instruction('i', $operation, new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame($expected, $result->native());
        self::assertSame($secret, $result->isSecret());
        self::assertSame(1, $state->iterators['cursor']->position);
    }
    /**
     * @return iterable<string,array{string,bool,int|string}>
     */
    public static function providerCursorReads(): iterable
    {
        yield 'public value' => ['iterator-value',false,20];
        yield 'secret value' => ['iterator-value',true,20];
        yield 'public key' => ['iterator-key',false,'second'];
        yield 'secret key' => ['iterator-key',true,'second'];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIterationAdvancement')]
    public function testEvaluateAdvancesExactlyOnePositionAndReportsAvailability(int $position, bool $secret, bool $expected): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['it'] = new \Deriver\Value\Term('iterator', 'cursor');
        $array = new \Deriver\Value\Term('array', operands:['first' => \Deriver\Value\Term::constant(10)], attributes:['open' => false], secret:$secret);
        $state->iterators['cursor'] = new \Deriver\Internal\Solver\Control\IteratorCursor($array, position:$position);
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'iterate', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame($expected, $result->native());
        self::assertSame($secret, $result->isSecret());
        self::assertSame($position + 1, $state->iterators['cursor']->position);
        self::assertSame($array, $state->iterators['cursor']->array);
    }
    /**
     * @return iterable<string,array{int,bool,bool}>
     */
    public static function providerIterationAdvancement(): iterable
    {
        yield 'entry' => [-1,false,true];
        yield 'exhausted' => [0,false,false];
        yield 'secret entry' => [-1,true,true];
        yield 'secret exhausted' => [0,true,false];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerUnknownIteration')]
    public function testEvaluateKeepsUnknownIterableAvailabilitySymbolic(\Deriver\Value\Term $array): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['it'] = new \Deriver\Value\Term('iterator', 'cursor');
        $state->iterators['cursor'] = new \Deriver\Internal\Solver\Control\IteratorCursor($array);
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'iterate', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame('external', $result->kind);
        self::assertSame('cursor:has-next:0', $result->literal);
        self::assertSame(['type' => 'bool'], $result->attributes);
        self::assertSame($array->secret, $result->secret);
        self::assertSame(0, $state->iterators['cursor']->position);
    }
    /**
     * @return iterable<string,array{\Deriver\Value\Term}>
     */
    public static function providerUnknownIteration(): iterable
    {
        yield 'unknown' => [\Deriver\Value\Term::parameter('items', 'iterable')];
        yield 'open' => [\Deriver\Value\Term::array([], true)];
        yield 'secret open' => [new \Deriver\Value\Term('array', attributes:['open' => true], secret:true)];
    }
    public function testEvaluateReleasesOnlyTheSelectedIterator(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $state->registers['it'] = new \Deriver\Value\Term('iterator', 'cursor');
        $location = $state->memory->allocate(\Deriver\Value\Term::fromNative([10]));
        $state->iterators['cursor'] = new \Deriver\Internal\Solver\Control\IteratorCursor($state->memory->read($location), $location);
        $other = new \Deriver\Internal\Solver\Control\IteratorCursor(\Deriver\Value\Term::array([]));
        $state->iterators['other'] = $other;
        $state->memory->liveArrays['cursor'] = new \Deriver\Internal\Memory\LiveArray($location, [0]);
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'iterator-release', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame(null, $result->native());
        self::assertSame(['other' => $other], $state->iterators);
        self::assertSame([], $state->memory->liveArrays);
        self::assertSame([10], $state->memory->read($location)->native());
    }
    public function testEvaluateReturnsAnExplicitBoundaryWhenNoCurrentEntryExists(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'iterator-value', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'result', ['missing']);
        $result = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame('opaque', $result->kind);
        self::assertSame('UNKNOWN_ITERABLE', $result->literal);
        self::assertSame([], $state->iterators);
    }
    public function testEvaluateResolvesLiveAddressesFromThePinnedStorage(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $location = $state->memory->allocate(\Deriver\Value\Term::fromNative(['key' => 10]));
        $state->registers['it'] = new \Deriver\Value\Term('iterator', 'cursor');
        $state->iterators['cursor'] = new \Deriver\Internal\Solver\Control\IteratorCursor(\Deriver\Value\Term::array([]), $location);
        $state->memory->liveArrays['cursor'] = new \Deriver\Internal\Memory\LiveArray($location, ['key']);
        $step = new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context());
        $source = new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1);
        $hasNext = $step->evaluate(new \Deriver\Internal\IR\Instruction('i', 'iterate', $source, 'next', ['it']), $state);
        $result = $step->evaluate(new \Deriver\Internal\IR\Instruction('a', 'iterator-address', $source, 'address', ['it']), $state);
        self::assertTrue($hasNext->native());
        self::assertSame('location', $result->kind);
        self::assertSame($location->root, $result->literal);
        self::assertSame(['key'], $state->addresses['address']->path);
        self::assertSame(10, $state->memory->read($state->addresses['address'])->native());
        $state->memory->write($state->addresses['address'], \Deriver\Value\Term::constant(20));
        self::assertSame(['key' => 20], $state->memory->read($location)->native());
    }
    public function testInitializeSnapshotsByValueWithoutAllocatingLiveStorage(): void
    {
        $state = new \Deriver\Internal\Solver\State();
        $array = \Deriver\Value\Term::fromNative([10]);
        $state->registers['array'] = $array;
        $instruction = new \Deriver\Internal\IR\Instruction('i', 'iterator', new \Deriver\Api\Reference\SourceRef('test', 'a.php', 0, 1), 'it', ['array']);
        $iterator = (new \Deriver\Internal\Solver\Control\IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertIsString($iterator->literal);
        self::assertSame($array, $state->iterators[$iterator->literal]->array);
        self::assertNull($state->iterators[$iterator->literal]->location);
        self::assertSame(-1, $state->iterators[$iterator->literal]->position);
        self::assertSame([], $state->memory->liveArrays);
        self::assertSame([], $state->memory->cells);
    }

}
