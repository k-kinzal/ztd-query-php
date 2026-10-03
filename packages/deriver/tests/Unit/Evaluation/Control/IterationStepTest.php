<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Control\IterationStep;
use Deriver\Evaluation\Control\IteratorCursor;
use Deriver\Evaluation\State;
use Deriver\Memory\LiveArray;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IterationStep::class)]
#[UsesClass(\Deriver\Analysis\QueryExecution::class)]
#[UsesClass(\Deriver\Analysis\QueryValidation::class)]
#[UsesClass(\Deriver\Analysis\ResultAssessment::class)]
#[UsesClass(\Deriver\Analysis\Session::class)]
#[UsesClass(\Deriver\Analyzer::class)]
#[UsesClass(\Deriver\Constraint\Constraints::class)]
#[UsesClass(\Deriver\ControlFlow\BasicBlock::class)]
#[UsesClass(\Deriver\ControlFlow\CallableGraph::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(Instruction::class)]
#[UsesClass(\Deriver\ControlFlow\Terminator::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\ArgumentOrder::class)]
#[UsesClass(\Deriver\Evaluation\Call\Dispatch::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeBinding::class)]
#[UsesClass(\Deriver\Evaluation\Call\TypeCheck::class)]
#[UsesClass(\Deriver\Evaluation\Completion::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(IteratorCursor::class)]
#[UsesClass(\Deriver\Evaluation\Control\LoopConvergence::class)]
#[UsesClass(\Deriver\Evaluation\Control\ObservationLimit::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Control\Unwinding::class)]
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(LiveArray::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Memory\StorageCapture::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
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
#[UsesClass(SourceRef::class)]
#[UsesClass(\Deriver\Result\Alternative::class)]
#[UsesClass(\Deriver\Result\Assessment::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
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
#[UsesClass(\Deriver\Source\Compilation\Control\LoopLowering::class)]
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
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Model\Builtin\TypePredicates::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
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
    public function testEvaluateNeverReadsOperandsOfSymbolicIterablesAsEntries(): void
    {
        $state = new State();
        $state->registers['it'] = new Term('iterator', 'cursor');
        $state->iterators['cursor'] = new IteratorCursor(new Term('intrinsic', 'explode', [Term::constant(','), Term::parameter('s', 'string')], ['type' => 'array']), position:0);
        $step = new IterationStep(\Tests\Fake\SolverFixture::context());
        $source = new SourceRef('test', 'a.php', 0, 1);
        self::assertSame('UNKNOWN_ITERABLE', $step->evaluate(new Instruction('i', 'iterator-value', $source, 'value', ['it']), $state)->literal);
        self::assertSame('UNKNOWN_ITERABLE', $step->evaluate(new Instruction('i', 'iterator-key', $source, 'key', ['it']), $state)->literal);
    }
    public function testAdvanceStoresTheCursorAndReportsKnownHeadEntries(): void
    {
        $state = new State();
        $merge = new Term('array-merge', operands:[Term::fromNative(['id']), Term::parameter('x', 'array')], attributes:['type' => 'array']);
        $cursor = new IteratorCursor($merge, position:0);
        $step = new IterationStep(\Tests\Fake\SolverFixture::context());
        self::assertTrue($step->advance('cursor', $cursor, Term::fromNative(['id']), $state)->native());
        self::assertSame($cursor, $state->iterators['cursor']);
        self::assertSame('external', $step->advance('cursor', new IteratorCursor($merge, position:1), Term::fromNative(['id']), $state)->kind);
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMergeHeads')]
    public function testEvaluateVisitsTheKnownHeadOfAMergeBeforeItsUnknownSource(int $position, string $operation, string $kind, mixed $expected): void
    {
        $state = new State();
        $state->registers['it'] = new Term('iterator', 'cursor');
        $merge = new Term('array-merge', operands:[Term::fromNative(['id', 'name']), Term::parameter('x', 'array')], attributes:['type' => 'array']);
        $state->iterators['cursor'] = new IteratorCursor($merge, position:$position);
        $result = (new IterationStep(\Tests\Fake\SolverFixture::context()))->evaluate(new Instruction('i', $operation, new SourceRef('test', 'a.php', 0, 1), 'result', ['it']), $state);
        self::assertSame($kind, $result->kind);
        self::assertSame($expected, $result->isConcrete() ? $result->native() : $result->literal);
    }
    /**
     * @return iterable<string,array{int,string,string,mixed}>
     */
    public static function providerMergeHeads(): iterable
    {
        yield 'first entry exists' => [-1,'iterate','constant',true];
        yield 'last head entry exists' => [0,'iterate','constant',true];
        yield 'source entries are unknown' => [1,'iterate','external','cursor:has-next:2'];
        yield 'head key' => [1,'iterator-key','constant',1];
        yield 'head value' => [1,'iterator-value','constant','name'];
        yield 'source value' => [2,'iterator-value','opaque','UNKNOWN_ITERABLE'];
    }
    public function testKeyIsCertainOnlyForTrackedClosedStorageAndKnownSnapshotEntries(): void
    {
        $state = new State();
        $location = $state->memory->allocate(Term::fromNative(['k' => 1]));
        $state->memory->liveArrays['live'] = new LiveArray($location, [], 'k');
        $step = new IterationStep(\Tests\Fake\SolverFixture::context());
        self::assertSame('k', $step->key('live', new IteratorCursor(Term::array([]), $location), Term::fromNative(['k' => 1]), null, $state));
        self::assertNull($step->key('live', new IteratorCursor(Term::array([]), $location), Term::parameter('a', 'array'), null, $state));
        self::assertSame(0, $step->key('snapshot', new IteratorCursor(Term::fromNative([1]), position: 0), Term::fromNative([1]), null, $state));
        $havocked = Term::array([new Term('opaque', 'UNKNOWN', attributes: ['maybeUninitialized' => true])], true);
        self::assertNull($step->key('snapshot', new IteratorCursor($havocked, position: 0), $havocked, null, $state));
    }
    public function testUnknownElementInvalidatesArrayOrObjectStorage(): void
    {
        $state = new State();
        $location = $state->memory->allocate(Term::fromNative([1]));
        $step = new IterationStep(\Tests\Fake\SolverFixture::context());
        $array = $step->unknownElement($state, $location, Term::parameter('a', 'array'));
        self::assertSame([$location->root, true], [$array->root, $array->unknown]);
        $object = $step->unknownElement($state, $location, new Term('object', 'o1', attributes: ['class' => 'stdClass']));
        self::assertSame(['object:o1', true], [$object->root, $object->unknown]);
        $untyped = $step->unknownElement($state, $location, Term::parameter('x'));
        self::assertTrue($untyped->unknown);
        self::assertSame('opaque', $state->memory->read($location)->kind);
    }
    public function testAdvanceForgetsTheTrackedBucketWhenLiveStorageBecomesUnknown(): void
    {
        $state = new State();
        $location = $state->memory->allocate(Term::parameter('a', 'array'));
        $state->memory->liveArrays['live'] = new LiveArray($location, [1], 0);
        $step = new IterationStep(\Tests\Fake\SolverFixture::context());
        self::assertSame('external', $step->advance('live', new IteratorCursor(Term::parameter('a', 'array'), $location, 1), null, $state)->kind);
        self::assertNull($state->memory->liveArrays['live']->current);
        self::assertSame([], $state->memory->liveArrays['live']->remaining);
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSubjects')]
    public function testIterableAcceptsOnlyArraysAndObjects(Term $subject, ?bool $expected): void
    {
        self::assertSame($expected, (new IterationStep(\Tests\Fake\SolverFixture::context()))->iterable($subject));
    }
    /**
     * @return iterable<string,array{Term,bool|null}>
     */
    public static function providerSubjects(): iterable
    {
        yield 'null' => [Term::constant(null), false];
        yield 'string' => [Term::constant('abc'), false];
        yield 'false' => [Term::constant(false), false];
        yield 'array' => [Term::array([]), true];
        yield 'object' => [new Term('object', 'o1', attributes:['class' => 'Item']), true];
        yield 'typed scalar' => [Term::parameter('rows', 'int|string'), false];
        yield 'typed array' => [Term::parameter('rows', 'array'), true];
        yield 'nullable array' => [Term::parameter('rows', 'array|null'), null];
        yield 'mixed' => [Term::parameter('rows'), null];
    }
    public function testInitializeSkipsANonIterableSubjectWithAWarning(): void
    {
        $state = new State();
        $state->registers['rows'] = Term::constant(null, true);
        $context = \Tests\Fake\SolverFixture::context();
        $step = new IterationStep($context);
        $source = new SourceRef('test', 'a.php', 0, 1);
        $state->registers['it'] = $step->evaluate(new Instruction('i', 'iterator', $source, 'it', ['rows']), $state);
        $next = $step->evaluate(new Instruction('n', 'iterate', $source, 'next', ['it']), $state);
        self::assertFalse($next->native());
        self::assertTrue($next->isSecret());
        self::assertSame(['foreach-non-iterable'], array_column(array_values($context->frontiers), 'operation'));
    }
    public function testReferencedLeavesAnUndefinedVariableUndefined(): void
    {
        $state = new State();
        $state->addresses['rows'] = $state->local('rows');
        $context = \Tests\Fake\SolverFixture::context();
        $instruction = new Instruction('i', 'iterator', new SourceRef('test', 'a.php', 0, 1), 'it', ['rows'], attributes:['byReference' => true]);
        (new IterationStep($context))->initialize($instruction, $state);
        self::assertSame('uninitialized', $state->memory->read($state->local('rows'))->kind);
        self::assertSame([], $state->memory->liveArrays);
        $frontiers = array_values($context->frontiers);
        self::assertSame(['uninitialized-read', 'foreach-non-iterable'], array_column($frontiers, 'operation'));
        self::assertSame(['variable:rows'], $frontiers[0]->knownDependencies);
    }
    public function testReferencedCreatesAMissingElementBeforeSkippingIt(): void
    {
        $state = new State();
        $root = $state->local('rows');
        $state->memory->write($root, Term::array([]));
        $state->addresses['element'] = new \Deriver\Memory\Location($root->root, ['k']);
        $instruction = new Instruction('i', 'iterator', new SourceRef('test', 'a.php', 0, 1), 'it', ['element'], attributes:['byReference' => true]);
        (new IterationStep(\Tests\Fake\SolverFixture::context()))->initialize($instruction, $state);
        self::assertSame(['k' => null], $state->memory->materialize($state->memory->read($root))->native());
    }

    public function testCandidatesRetainsTheUnknownPrefixBesideAKnownSuffix(): void
    {
        $context = \Tests\Fake\SolverFixture::context();
        $state = new State();
        $arrays = new \Deriver\Value\Arrays();
        $array = $arrays->set($arrays->merge(Term::array([]), Term::parameter('x', 'array')), null, Term::constant('z'));
        $state->registers['it'] = new Term('iterator', 'cursor');
        $state->iterators['cursor'] = new IteratorCursor($array, position: 0);
        $paths = (new IterationStep($context))->candidates(new Instruction('v', 'iterator-value', new SourceRef('test', 'x', 0, 1), 'out', ['it']), $state);
        self::assertNotNull($paths);
        self::assertCount(2, $paths);
        self::assertSame('z', $paths[0]->value('out')->native());
        self::assertFalse($paths[1]->value('out')->isConcrete());
    }

}
