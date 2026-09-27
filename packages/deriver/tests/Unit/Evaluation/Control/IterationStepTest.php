<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Analysis\QueryExecution;
use Deriver\Analysis\QueryValidation;
use Deriver\Analysis\ResultAssessment;
use Deriver\Analysis\Session;
use Deriver\Analyzer;
use Deriver\Constraint\Constraints;
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
use Deriver\Evaluation\Control\IterationStep;
use Deriver\Evaluation\Control\IteratorCursor;
use Deriver\Evaluation\Control\LoopConvergence;
use Deriver\Evaluation\Control\ObservationLimit;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Control\Unwinding;
use Deriver\Evaluation\Demand\Discovery;
use Deriver\Evaluation\Dependencies;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Model\SlotReference;
use Deriver\Evaluation\ObservationCollector;
use Deriver\Evaluation\Operation\Conversions;
use Deriver\Evaluation\Operation\ScalarErrors;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Summary\Evaluation;
use Deriver\Evaluation\Summary\Isolation;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\PureStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\LiveArray;
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
use Deriver\Source\Compilation\Control\LoopLowering;
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
use Deriver\Value\Arrays;
use Deriver\Value\Identity;
use Deriver\Value\Operations;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IterationStep::class)]
#[UsesClass(QueryExecution::class)]
#[UsesClass(QueryValidation::class)]
#[UsesClass(ResultAssessment::class)]
#[UsesClass(Session::class)]
#[UsesClass(Analyzer::class)]
#[UsesClass(Constraints::class)]
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
#[UsesClass(IteratorCursor::class)]
#[UsesClass(LoopConvergence::class)]
#[UsesClass(ObservationLimit::class)]
#[UsesClass(Resources::class)]
#[UsesClass(StateJoin::class)]
#[UsesClass(Unwinding::class)]
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Machine::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Conversions::class)]
#[UsesClass(ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(Evaluation::class)]
#[UsesClass(Isolation::class)]
#[UsesClass(MemoryStep::class)]
#[UsesClass(PureStep::class)]
#[UsesClass(ReferenceAssignment::class)]
#[UsesClass(LiveArray::class)]
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
#[UsesClass(LoopLowering::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
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
        $state = new State();
        $state->addresses['array'] = $state->memory->allocate(Term::fromNative([1,2]));
        $instruction = new Instruction('i', 'iterator', new SourceRef('test', 'a.php', 0, 1), 'it', ['array'], attributes:['byReference' => true]);
        $iterator = (new IterationStep(\Tests\Fake\SolverFixture::context()))->initialize($instruction, $state);
        self::assertCount(1, $state->memory->liveArrays);
        self::assertSame('iterator', $iterator->kind);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCursorReads')]
    public function testEvaluateReadsCursorValuesAndKeysWithContainerConfidentiality(string $operation, bool $secret, mixed $expected): void
    {
        $state = new State();
        $state->registers['it'] = new Term('iterator', 'cursor');
        $array = new Term('array', operands:['first' => Term::constant(10),'second' => Term::constant(20)], attributes:['open' => false], secret:$secret);
        $state->iterators['cursor'] = new IteratorCursor($array, position:1);
        $instruction = new Instruction('i', $operation, new SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
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
        $state = new State();
        $state->registers['it'] = new Term('iterator', 'cursor');
        $array = new Term('array', operands:['first' => Term::constant(10)], attributes:['open' => false], secret:$secret);
        $state->iterators['cursor'] = new IteratorCursor($array, position:$position);
        $instruction = new Instruction('i', 'iterate', new SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
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
    public function testEvaluateKeepsUnknownIterableAvailabilitySymbolic(Term $array): void
    {
        $state = new State();
        $state->registers['it'] = new Term('iterator', 'cursor');
        $state->iterators['cursor'] = new IteratorCursor($array);
        $instruction = new Instruction('i', 'iterate', new SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame('external', $result->kind);
        self::assertSame('cursor:has-next:0', $result->literal);
        self::assertSame(['type' => 'bool'], $result->attributes);
        self::assertSame($array->secret, $result->secret);
        self::assertSame(0, $state->iterators['cursor']->position);
    }
    /**
     * @return iterable<string,array{Term}>
     */
    public static function providerUnknownIteration(): iterable
    {
        yield 'unknown' => [Term::parameter('items', 'iterable')];
        yield 'open' => [Term::array([], true)];
        yield 'secret open' => [new Term('array', attributes:['open' => true], secret:true)];
    }
    public function testEvaluateReleasesOnlyTheSelectedIterator(): void
    {
        $state = new State();
        $state->registers['it'] = new Term('iterator', 'cursor');
        $location = $state->memory->allocate(Term::fromNative([10]));
        $state->iterators['cursor'] = new IteratorCursor($state->memory->read($location), $location);
        $other = new IteratorCursor(Term::array([]));
        $state->iterators['other'] = $other;
        $state->memory->liveArrays['cursor'] = new LiveArray($location, [0]);
        $instruction = new Instruction('i', 'iterator-release', new SourceRef('test', 'a.php', 0, 1), 'result', ['it']);
        $result = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame(null, $result->native());
        self::assertSame(['other' => $other], $state->iterators);
        self::assertSame([], $state->memory->liveArrays);
        self::assertSame([10], $state->memory->read($location)->native());
    }
    public function testEvaluateReturnsAnExplicitBoundaryWhenNoCurrentEntryExists(): void
    {
        $state = new State();
        $instruction = new Instruction('i', 'iterator-value', new SourceRef('test', 'a.php', 0, 1), 'result', ['missing']);
        $result = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertSame('opaque', $result->kind);
        self::assertSame('UNKNOWN_ITERABLE', $result->literal);
        self::assertSame([], $state->iterators);
    }
    public function testEvaluateResolvesLiveAddressesFromThePinnedStorage(): void
    {
        $state = new State();
        $location = $state->memory->allocate(Term::fromNative(['key' => 10]));
        $state->registers['it'] = new Term('iterator', 'cursor');
        $state->iterators['cursor'] = new IteratorCursor(Term::array([]), $location);
        $state->memory->liveArrays['cursor'] = new LiveArray($location, ['key']);
        $step = new IterationStep(\Tests\Fake\SolverFixture::context());
        $source = new SourceRef('test', 'a.php', 0, 1);
        $hasNext = $step->evaluate(new Instruction('i', 'iterate', $source, 'next', ['it']), $state);
        $result = $step->evaluate(new Instruction('a', 'iterator-address', $source, 'address', ['it']), $state);
        self::assertTrue($hasNext->native());
        self::assertSame('location', $result->kind);
        self::assertSame($location->root, $result->literal);
        self::assertSame(['key'], $state->addresses['address']->path);
        self::assertSame(10, $state->memory->read($state->addresses['address'])->native());
        $state->memory->write($state->addresses['address'], Term::constant(20));
        self::assertSame(['key' => 20], $state->memory->read($location)->native());
    }
    public function testInitializeSnapshotsByValueWithoutAllocatingLiveStorage(): void
    {
        $state = new State();
        $array = Term::fromNative([10]);
        $state->registers['array'] = $array;
        $instruction = new Instruction('i', 'iterator', new SourceRef('test', 'a.php', 0, 1), 'it', ['array']);
        $iterator = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate($instruction, $state);
        self::assertIsString($iterator->literal);
        self::assertSame($array, $state->iterators[$iterator->literal]->array);
        self::assertNull($state->iterators[$iterator->literal]->location);
        self::assertSame(-1, $state->iterators[$iterator->literal]->position);
        self::assertSame([], $state->memory->liveArrays);
        self::assertSame([], $state->memory->cells);
    }

}
