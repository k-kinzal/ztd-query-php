<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Memory;

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
use Deriver\Deriver\Evaluator\Execution\Demand\Discovery;
use Deriver\Deriver\Evaluator\Execution\Dependencies;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\EvaluatorConfig as ExecutionEvaluatorConfig;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\LiveArray;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Execution\Memory\ReferenceConstraint;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\StorageCapture;
use Deriver\Deriver\Evaluator\Execution\ObservationCollector;
use Deriver\Deriver\Evaluator\Execution\Offset\Address;
use Deriver\Deriver\Evaluator\Execution\Offset\Path;
use Deriver\Deriver\Evaluator\Execution\Offset\Reader;
use Deriver\Deriver\Evaluator\Execution\Offset\Transfer;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Summary\Evaluation;
use Deriver\Deriver\Evaluator\Execution\Summary\Isolation;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[UsesClass(Resolver::class)]
#[UsesClass(ExpressionValidation::class)]
#[UsesClass(ResolutionException::class)]
#[UsesClass(ParserConfig::class)]
#[UsesClass(ParserException::class)]
#[CoversClass(Memory::class)]
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
#[UsesClass(Discovery::class)]
#[UsesClass(Dependencies::class)]
#[UsesClass(InstructionTransfer::class)]
#[UsesClass(Evaluator::class)]
#[UsesClass(SlotReference::class)]
#[UsesClass(ObservationCollector::class)]
#[UsesClass(Address::class)]
#[UsesClass(Path::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Transfer::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(Identity::class)]
#[UsesClass(Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class MemoryTest extends TestCase
{
    public function testFreshPreservesTheSemanticContract(): void
    {
        $source = '<?php function target(){$x=1;$a=[&$x];$b=$a;$b[0]=2;return [$x,$a,$b];}';
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
        self::assertSame([2, [2], [2]], $result->normalOutcomes[0]->values['return']->native());
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
    }
    public function testAllocateCreatesIndependentCells(): void
    {
        $memory = new Memory();
        $a = $memory->allocate(Term::constant(1));
        $b = $memory->allocate(Term::constant(1));
        $memory->write($b, Term::constant(2));
        self::assertNotSame($a->root, $b->root);
        self::assertSame(1, $memory->read($a)->native());
        self::assertSame(2, $memory->read($b)->native());
    }
    public function testReadDistinguishesUninitializedFromNull(): void
    {
        $memory = new Memory();
        $null = $memory->allocate(Term::constant(null));
        self::assertSame(TermKind::Uninitialized, $memory->read(new Location('missing'))->kind);
        self::assertSame(TermKind::Constant, $memory->read($null)->kind);
    }
    public function testDereferenceStopsAtReferenceCycles(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = new Term(TermKind::Cell, 'b');
        $memory->cells['b'] = new Term(TermKind::Cell, 'a');
        self::assertSame('CYCLIC_REFERENCE', $memory->dereference(new Term(TermKind::Cell, 'a'))->literal);
    }
    public function testWritePreservesUnrelatedArrayFields(): void
    {
        $memory = new Memory();
        $a = $memory->allocate(Term::fromNative(['a' => 1, 'b' => 2]));
        $memory->write(new Location($a->root, ['a']), Term::constant(3));
        self::assertSame(['a' => 3, 'b' => 2], $memory->read($a)->native());
        self::assertSame(1, $memory->versions[$a->root]);
    }
    public function testWritePathRebindsOnlyTheFinalReferenceSlot(): void
    {
        $memory = new Memory();
        $a = $memory->allocate(Term::constant(1));
        $b = $memory->allocate(Term::constant(2));
        $record = $memory->allocate(Term::array([new Term(TermKind::Cell, $a->root)]));
        $memory->writePath($record->root, [0], new Term(TermKind::Cell, $b->root), true);
        $memory->write(new Location($record->root, [0]), Term::constant(3));
        self::assertSame(1, $memory->read($a)->native());
        self::assertSame(3, $memory->read($b)->native());
    }
    public function testReplacePreservesNestedStructure(): void
    {
        $memory = new Memory();
        $result = $memory->replace(Term::fromNative(['a' => ['x' => 1, 'y' => 2]]), ['a', 'x'], Term::constant(3));
        self::assertSame(['a' => ['x' => 3, 'y' => 2]], $result->native());
    }
    public function testReferenceMaterializesOneSharedCell(): void
    {
        $memory = new Memory();
        $array = $memory->allocate(Term::fromNative([1]));
        $element = new Location($array->root, [0]);
        $first = $memory->reference($element);
        $second = $memory->reference($element);
        self::assertSame($first, $second);
        $memory->write(new Location($first), Term::constant(2));
        self::assertSame(2, $memory->read($element)->native());
    }
    public function testRawKeepsTheReferenceIdentity(): void
    {
        $memory = new Memory();
        $cell = $memory->allocate(Term::constant(1));
        $array = $memory->allocate(Term::array([new Term(TermKind::Cell, $cell->root)]));
        self::assertSame(TermKind::Cell, $memory->raw(new Location($array->root, [0]))->kind);
        self::assertSame(1, $memory->read(new Location($array->root, [0]))->native());
    }
    public function testRemoveKeepsTheNextAppendIndex(): void
    {
        $memory = new Memory();
        $array = $memory->allocate(Term::fromNative([5 => 'a']));
        $memory->remove(new Location($array->root, [5]));
        self::assertSame(6, (new Arrays())->next($memory->read($array)));
        self::assertSame([], $memory->read($array)->native());
    }
    public function testMaterializeReadsReferencedArrayElements(): void
    {
        $memory = new Memory();
        $cell = $memory->allocate(Term::constant('shared'));
        self::assertSame(['shared'], $memory->materialize(Term::array([new Term(TermKind::Cell, $cell->root)]))->native());
    }
    public function testSynchronizeKeepsLiveTraversalThroughElementDeletion(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = Term::fromNative([10, 20, 30]);
        $memory->liveArrays['i'] = new LiveArray(new Location('a'), [1, 2], 0);
        $memory->remove(new Location('a', [0]));
        self::assertSame([1, 2], $memory->liveArrays['i']->remaining);
        $memory->write(new Location('a'), Term::fromNative([40, 50]));
        self::assertSame([0, 1], $memory->liveArrays['i']->remaining);
    }
    public function testRemovePreservesUnrelatedArrayEntries(): void
    {
        $memory = new Memory();
        $value = new Term(TermKind::Array, operands: ['private' => Term::constant('derived'), 'drop' => Term::constant(1)], attributes: ['open' => false]);
        $location = $memory->allocate($value);
        $memory->remove(new Location($location->root, ['drop']));
        $remaining = $memory->read($location);
        self::assertSame(['private' => 'derived'], $remaining->native());
    }
    public function testElementPreservesOpenAndClosedAbsenceAndReferenceIdentity(): void
    {
        $memory = new Memory();
        $object = new Term(TermKind::Object, 'one', attributes: ['class' => 'Box']);
        $location = $memory->allocate($object);
        $array = new Term(TermKind::Array, operands: ['object' => new Term(TermKind::Cell, $location->root)], attributes: ['open' => false]);
        $entry = $memory->element($array, 'object');
        self::assertSame('one', $entry->literal);
        self::assertSame(['class' => 'Box'], $entry->attributes);
        self::assertSame(TermKind::Uninitialized, $memory->element($array, 'missing')->kind);
        self::assertSame('UNKNOWN_ARRAY_KEY', $memory->element(Term::array([], true), 'missing')->literal);
        self::assertSame($object, $memory->read($location));
    }
    public function testReadSelectsNestedArrayEntries(): void
    {
        $memory = new Memory();
        $array = new Term(TermKind::Array, operands: ['outer' => Term::fromNative(['inner' => 'private-value'])], attributes: ['open' => false]);
        $location = $memory->allocate($array);
        $result = $memory->read(new Location($location->root, ['outer', 'inner']));
        self::assertSame('private-value', $result->native());
        self::assertSame($array, $memory->read($location));
    }
    public function testDereferenceResolvesCellChainsAndDetectsCycles(): void
    {
        $memory = new Memory();
        $memory->cells['first'] = new Term(TermKind::Cell, 'second');
        $memory->cells['second'] = Term::constant('private-value');
        $value = $memory->dereference(new Term(TermKind::Cell, 'first'));
        $memory->cells['second'] = new Term(TermKind::Cell, 'first');
        $cycle = $memory->dereference(new Term(TermKind::Cell, 'first'));
        self::assertSame('private-value', $value->native());
        self::assertSame('CYCLIC_REFERENCE', $cycle->literal);
    }
    public function testReferenceReusesAnExistingArrayCell(): void
    {
        $memory = new Memory();
        $value = $memory->allocate(Term::constant('private-value'));
        $link = $memory->allocate(new Term(TermKind::Cell, $value->root));
        $array = $memory->allocate(new Term(TermKind::Array, operands: ['value' => new Term(TermKind::Cell, $link->root)], attributes: ['open' => false]));
        $reference = $memory->reference(new Location($array->root, ['value']));
        self::assertSame($link->root, $reference);
        self::assertSame('private-value', $memory->read(new Location($reference))->native());
        $memory->write($value, Term::constant('updated'));
        self::assertSame('updated', $memory->read(new Location($reference))->native());
    }
    public function testFreshSeparatesAllocationCategoriesAcrossOneMonotonicSequence(): void
    {
        $memory = new Memory();
        self::assertSame('object:0', $memory->fresh('object'));
        self::assertSame('cell:1', $memory->fresh('cell'));
        self::assertSame('object:2', $memory->fresh('object'));
        self::assertSame(3, $memory->sequence);
    }
    public function testReadKeepsUnknownAddressesOpaqueEvenWhenTheirRootContainsAKnownValue(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = Term::constant(1);
        $value = $memory->read(new Location('a', unknown: true));
        self::assertSame(TermKind::Opaque, $value->kind);
        self::assertSame('UNKNOWN_LOCATION', $value->literal);
        self::assertSame('partial', $value->attributes['dependencyCoverage']);
    }
    public function testReadRetainsAResidualArrayAccessAndMissingStorage(): void
    {
        $memory = new Memory();
        $parameter = Term::parameter('value');
        $memory->cells['a'] = $parameter;
        $value = $memory->read(new Location('a', ['key']));
        self::assertSame(TermKind::ArrayRead, $value->kind);
        self::assertSame($parameter, $value->operands[0]);
        self::assertSame('key', $value->operands[1]->literal);
        self::assertSame(TermKind::Uninitialized, $memory->read(new Location('missing', ['nested', 'key']))->kind);
    }
    public function testElementTreatsAnUnmarkedShapeAsClosed(): void
    {
        $memory = new Memory();
        $array = new Term(TermKind::Array, operands: ['key' => Term::constant(1)]);
        self::assertSame(TermKind::Uninitialized, $memory->element($array, 'missing')->kind);
    }
    public function testDereferenceResolvesReferencesAndMarksCyclesAsIncomplete(): void
    {
        $memory = new Memory();
        $value = Term::constant('public');
        $memory->cells['a'] = new Term(TermKind::Cell, 'b');
        $memory->cells['b'] = $value;
        self::assertSame($value, $memory->dereference(new Term(TermKind::Cell, 'a')));
        $memory->cells['b'] = new Term(TermKind::Cell, 'a');
        $cycle = $memory->dereference(new Term(TermKind::Cell, 'a'));
        self::assertSame(TermKind::Opaque, $cycle->kind);
        self::assertSame('CYCLIC_REFERENCE', $cycle->literal);
        self::assertSame(['type' => 'mixed', 'dependencyCoverage' => 'partial'], $cycle->attributes);
        self::assertSame(TermKind::Uninitialized, $memory->dereference(new Term(TermKind::Cell, 'missing'))->kind);
    }
    public function testWriteTracksEveryVersionAndRetainsUnknownWriteDependencies(): void
    {
        $memory = new Memory();
        $location = new Location('new');
        $memory->write($location, Term::constant(1));
        $first = $memory->versions['new'];
        $memory->write($location, Term::constant(2));
        $second = $memory->versions['new'];
        $dependency = Term::constant('private');
        $memory->write(new Location('new', unknown: true), $dependency);
        self::assertSame(1, $first);
        self::assertSame(2, $second);
        self::assertSame(3, $memory->versions['new']);
        self::assertSame('UNKNOWN_WRITE', $memory->cells['new']->literal);
        self::assertSame([$dependency], $memory->cells['new']->operands);
    }
    public function testWritePathFollowsRootAliasesAndUpdatesTheirLiveArrayCursor(): void
    {
        $memory = new Memory();
        $memory->cells['alias'] = new Term(TermKind::Cell, 'array');
        $memory->cells['array'] = Term::fromNative([10, 20]);
        $memory->liveArrays['cursor'] = new LiveArray(new Location('array'), [1], 0);
        $memory->writePath('alias', [0], Term::constant(30));
        $afterElement = $memory->liveArrays['cursor']->remaining;
        $memory->writePath('alias', [], Term::fromNative([40, 50]));
        self::assertSame([1], $afterElement);
        self::assertSame([0, 1], $memory->liveArrays['cursor']->remaining);
        self::assertSame([40, 50], $memory->read(new Location('alias'))->native());
        self::assertSame(TermKind::Cell, $memory->cells['alias']->kind);
        self::assertSame('array', $memory->cells['alias']->literal);
    }
    public function testReplaceKeepsAReferenceWrapperAndReplacesTheReferencedArray(): void
    {
        $memory = new Memory();
        $memory->cells['array'] = Term::fromNative([10, 20]);
        $memory->liveArrays['cursor'] = new LiveArray(new Location('array'), [1], 0);
        $reference = new Term(TermKind::Cell, 'array');
        $result = $memory->replace($reference, [], Term::fromNative([30, 40]));
        self::assertSame($reference, $result);
        self::assertSame([30, 40], $memory->cells['array']->native());
        self::assertSame([0, 1], $memory->liveArrays['cursor']->remaining);
    }
    #[DataProvider('providerReplacementBases')]
    public function testReplaceDistinguishesEmptyStorageFromUnknownExistingKeys(Term $before, bool $open): void
    {
        $result = (new Memory())->replace($before, ['nested', 'key'], Term::constant(7));
        self::assertSame(TermKind::Array, $result->kind);
        self::assertSame($open, $result->attributes['open']);
        self::assertSame(false, $result->operands['nested']->attributes['open']);
        self::assertSame(7, $result->operands['nested']->operands['key']->literal);
    }
    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerReplacementBases(): iterable
    {
        yield 'uninitialized' => [new Term(TermKind::Uninitialized), false];
        yield 'null' => [Term::constant(null), false];
        yield 'unknown parameter' => [Term::parameter('x'), true];
        yield 'known closed array' => [Term::fromNative(['other' => 1]), false];
        yield 'open array' => [Term::array([], true), true];
    }
    public function testReferenceInitializesMissingStorageAndFollowsExistingRootAliases(): void
    {
        $memory = new Memory();
        $memory->cells['alias'] = new Term(TermKind::Cell, 'target');
        $root = $memory->reference(new Location('alias'));
        self::assertSame('target', $root);
        self::assertSame(TermKind::Constant, $memory->cells['target']->kind);
        self::assertNull($memory->cells['target']->literal);
        self::assertSame('target', $memory->cells['alias']->literal);
        self::assertSame('unseen', $memory->reference(new Location('unseen', unknown: true)));
        self::assertArrayNotHasKey('unseen', $memory->cells);
    }
    public function testReferenceTerminatesForCyclesAndDoesNotMistakeStringsForCellLinks(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = new Term(TermKind::Cell, 'b');
        $memory->cells['b'] = new Term(TermKind::Cell, 'a');
        $memory->cells['text'] = Term::constant('a');
        self::assertSame('a', $memory->reference(new Location('a')));
        self::assertSame('text', $memory->reference(new Location('text')));
    }
    public function testReferenceMaterializesStringElements(): void
    {
        $memory = new Memory();
        $array = $memory->allocate(Term::fromNative(['key' => 'text']));
        $location = new Location($array->root, ['key']);
        $first = $memory->reference($location);
        $second = $memory->reference($location);
        self::assertSame($first, $second);
        self::assertSame('text', $memory->cells[$first]->literal);
        self::assertSame(TermKind::Constant, $memory->cells[$first]->kind);
        self::assertSame(TermKind::Cell, $memory->raw($location)->kind);
    }
    public function testRemoveRootLeavesOtherCellsUntouched(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = Term::constant(1);
        $memory->cells['b'] = Term::constant(2);
        $memory->remove(new Location('a'));
        self::assertSame(['b'], array_keys($memory->cells));
        self::assertSame(2, $memory->cells['b']->literal);
    }
    public function testRemoveRetainsOpenShapeMetadataAndDoesNotRestartLiveIteration(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = new Term(TermKind::Array, operands: [0 => Term::constant(10), 1 => Term::constant(20), 2 => Term::constant(30)], attributes: ['open' => true, 'next' => 8]);
        $memory->liveArrays['cursor'] = new LiveArray(new Location('a'), [2], 1);
        $memory->remove(new Location('a', [1]));
        self::assertSame(['open' => true, 'next' => null], $memory->cells['a']->attributes);
        self::assertSame([0, 2], array_keys($memory->cells['a']->operands));
        self::assertSame([2], $memory->liveArrays['cursor']->remaining);
    }
    public function testSynchronizeChangesOnlyCursorsBoundToTheMutatedRoot(): void
    {
        $memory = new Memory();
        $before = Term::fromNative([1, 2]);
        $after = Term::fromNative([3, 4, 5]);
        $cursor = new LiveArray(new Location('a'), [1], 0);
        $other = new LiveArray(new Location('b'), [1], 0);
        $memory->liveArrays = ['chosen' => $cursor, 'other' => $other];
        $memory->synchronize('a', $before, $after, false);
        self::assertSame([1, 2], $memory->liveArrays['chosen']->remaining);
        self::assertSame($other, $memory->liveArrays['other']);
        self::assertSame([1], $cursor->remaining);
    }
    public function testReadKeepsEveryKeyInAnUnresolvedNestedPath(): void
    {
        $memory = new Memory();
        $input = Term::parameter('record');
        $memory->cells['a'] = $input;
        $value = $memory->read(new Location('a', ['outer', 'inner']));
        self::assertSame(TermKind::ArrayRead, $value->kind);
        self::assertSame('inner', $value->operands[1]->literal);
        self::assertSame(TermKind::ArrayRead, $value->operands[0]->kind);
        self::assertSame('outer', $value->operands[0]->operands[1]->literal);
        self::assertSame($input, $value->operands[0]->operands[0]);
    }
    public function testReferenceMovesAGlobalValueIntoASharedCellBehindTheSlot(): void
    {
        $memory = new Memory();
        $memory->cells['global:x'] = Term::constant(1);
        $shared = $memory->reference(new Location('global:x'));
        self::assertNotSame('global:x', $shared);
        self::assertSame(['cell', $shared], [$memory->cells['global:x']->kind->value, $memory->cells['global:x']->literal]);
        self::assertSame($shared, $memory->reference(new Location('global:x')));
        $memory->cells['global:x'] = new Term(TermKind::Uninitialized);
        self::assertSame(1, $memory->read(new Location($shared))->native());
    }
}
