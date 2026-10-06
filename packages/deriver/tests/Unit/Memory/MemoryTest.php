<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Deriver\Memory\LiveArray;
use Deriver\Memory\Location;
use Deriver\Memory\Memory;
use Deriver\Value\Arrays;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Memory::class)]
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
#[UsesClass(\Deriver\Evaluation\Demand\Discovery::class)]
#[UsesClass(\Deriver\Evaluation\Dependencies::class)]
#[UsesClass(\Deriver\Evaluation\InstructionTransfer::class)]
#[UsesClass(\Deriver\Evaluation\Machine::class)]
#[UsesClass(\Deriver\Evaluation\Model\SlotReference::class)]
#[UsesClass(\Deriver\Evaluation\ObservationCollector::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Address::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Path::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Reader::class)]
#[UsesClass(\Deriver\Evaluation\Offset\Transfer::class)]
#[UsesClass(\Deriver\Evaluation\Operation\Conversions::class)]
#[UsesClass(\Deriver\Evaluation\Operation\ScalarErrors::class)]
#[UsesClass(\Deriver\Evaluation\State::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Evaluation::class)]
#[UsesClass(\Deriver\Evaluation\Summary\Isolation::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\MemoryStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\PureStep::class)]
#[UsesClass(\Deriver\Evaluation\Transfer\ReferenceAssignment::class)]
#[UsesClass(LiveArray::class)]
#[UsesClass(Location::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
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
#[UsesClass(Arrays::class)]
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class MemoryTest extends TestCase
{
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testFreshPreservesTheSemanticContract(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(){$x=1;$a=[&$x];$b=$a;$b[0]=2;return [$x,$a,$b];}');
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
        self::assertSame('uninitialized', $memory->read(new Location('missing'))->kind);
        self::assertSame('constant', $memory->read($null)->kind);
    }

    public function testDereferenceStopsAtReferenceCycles(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = new Term('cell', 'b');
        $memory->cells['b'] = new Term('cell', 'a');
        self::assertSame('CYCLIC_REFERENCE', $memory->dereference(new Term('cell', 'a'))->literal);
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
        $record = $memory->allocate(Term::array([new Term('cell', $a->root)]));
        $memory->writePath($record->root, [0], new Term('cell', $b->root), true);
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
        $array = $memory->allocate(Term::array([new Term('cell', $cell->root)]));
        self::assertSame('cell', $memory->raw(new Location($array->root, [0]))->kind);
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
        self::assertSame(['shared'], $memory->materialize(Term::array([new Term('cell', $cell->root)]))->native());
    }

    public function testSynchronizeKeepsLiveTraversalThroughElementDeletion(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = Term::fromNative([10,20,30]);
        $memory->liveArrays['i'] = new LiveArray(new Location('a'), [1,2], 0);
        $memory->remove(new Location('a', [0]));
        self::assertSame([1,2], $memory->liveArrays['i']->remaining);
        $memory->write(new Location('a'), Term::fromNative([40,50]));
        self::assertSame([0,1], $memory->liveArrays['i']->remaining);
    }

    public function testRemovePreservesAnExplicitSecretLabelOnTheAggregate(): void
    {
        $memory = new Memory();
        $value = new Term('array', operands:['private' => Term::constant('derived'),'drop' => Term::constant(1)], attributes:['open' => false], secret:true);
        $location = $memory->allocate($value);
        $memory->remove(new Location($location->root, ['drop']));
        $remaining = $memory->read($location);
        self::assertSame(['private' => 'derived'], $remaining->native());
        self::assertTrue($remaining->isSecret());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerElementConfidentiality')]
    public function testElementPreservesContainerKeyAndSelectedValueConfidentiality(bool $containerSecret, bool $keySecret, bool $childSecret, bool $expected): void
    {
        $child = Term::constant('private-value', $childSecret);
        $array = new Term('array', operands: ['chosen' => $child], attributes: ['open' => false], secret: $containerSecret);
        $result = (new Memory())->element($array, 'chosen', $keySecret);
        self::assertSame('private-value', $result->native());
        self::assertSame($expected, $result->isSecret());
        self::assertSame($childSecret, $child->secret);
    }
    /**
     * @return iterable<string, array{bool,bool,bool,bool}>
     */
    public static function providerElementConfidentiality(): iterable
    {
        yield 'public' => [false,false,false,false];
        yield 'container' => [true,false,false,true];
        yield 'key' => [false,true,false,true];
        yield 'child' => [false,false,true,true];
        yield 'container and key' => [true,true,false,true];
        yield 'all' => [true,true,true,true];
    }
    public function testElementPreservesOpenAndClosedAbsenceAndReferenceIdentity(): void
    {
        $memory = new Memory();
        $object = new Term('object', 'one', attributes: ['class' => 'Box']);
        $location = $memory->allocate($object);
        $array = new Term('array', operands: ['object' => new Term('cell', $location->root)], attributes:['open' => false], secret:true);
        $entry = $memory->element($array, 'object');
        self::assertSame('one', $entry->literal);
        self::assertSame(['class' => 'Box'], $entry->attributes);
        self::assertTrue($entry->secret);
        self::assertSame('uninitialized', $memory->element($array, 'missing')->kind);
        self::assertTrue($memory->element($array, 'missing')->secret);
        self::assertSame('UNKNOWN_ARRAY_KEY', $memory->element(Term::array([], true), 'missing')->literal);
        self::assertSame($object, $memory->read($location));
    }
    public function testReadCarriesAnOuterSecretLabelThroughNestedArrays(): void
    {
        $memory = new Memory();
        $array = new Term('array', operands:['outer' => Term::fromNative(['inner' => 'private-value'])], attributes:['open' => false], secret:true);
        $location = $memory->allocate($array);
        $result = $memory->read(new Location($location->root, ['outer','inner']));
        self::assertSame('private-value', $result->native());
        self::assertTrue($result->isSecret());
        self::assertSame($array, $memory->read($location));
    }
    public function testDereferenceRetainsSecretLabelsAcrossCellChainsAndCycles(): void
    {
        $memory = new Memory();
        $memory->cells['first'] = new Term('cell', 'second', secret:true);
        $memory->cells['second'] = Term::constant('private-value');
        $value = $memory->dereference(new Term('cell', 'first'));
        $memory->cells['second'] = new Term('cell', 'first');
        $cycle = $memory->dereference(new Term('cell', 'first'));
        self::assertTrue($value->secret);
        self::assertSame('private-value', $value->native());
        self::assertSame('CYCLIC_REFERENCE', $cycle->literal);
        self::assertTrue($cycle->secret);
    }
    public function testReferenceRetainsConfidentialityWhenAnExistingArrayCellEscapes(): void
    {
        $memory = new Memory();
        $value = $memory->allocate(Term::constant('private-value'));
        $link = $memory->allocate(new Term('cell', $value->root));
        $array = $memory->allocate(new Term('array', operands:['value' => new Term('cell', $link->root)], attributes:['open' => false], secret:true));
        $reference = $memory->reference(new Location($array->root, ['value']));
        self::assertSame($link->root, $reference);
        self::assertSame('private-value', $memory->read(new Location($reference))->native());
        self::assertTrue($memory->read(new Location($reference))->secret);
        $memory->write($value, Term::constant('updated'));
        self::assertSame('updated', $memory->read(new Location($reference))->native());
        self::assertTrue($memory->read(new Location($reference))->secret);
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
        $value = $memory->read(new Location('a', unknown:true));
        self::assertSame('opaque', $value->kind);
        self::assertSame('UNKNOWN_LOCATION', $value->literal);
        self::assertSame('partial', $value->attributes['dependencyCoverage']);
    }

    public function testReadRetainsAResidualArrayAccessAndMissingStorage(): void
    {
        $memory = new Memory();
        $parameter = Term::parameter('value');
        $memory->cells['a'] = $parameter;
        $value = $memory->read(new Location('a', ['key']));
        self::assertSame('array-read', $value->kind);
        self::assertSame($parameter, $value->operands[0]);
        self::assertSame('key', $value->operands[1]->literal);
        self::assertSame('uninitialized', $memory->read(new Location('missing', ['nested','key']))->kind);
    }

    public function testElementTreatsAnUnmarkedShapeAsClosedAndPublic(): void
    {
        $memory = new Memory();
        $array = new Term('array', operands:['key' => Term::constant(1)]);
        self::assertFalse($memory->element($array, 'key')->secret);
        self::assertSame('uninitialized', $memory->element($array, 'missing')->kind);
        self::assertFalse($memory->element($array, 'missing')->secret);
    }

    public function testDereferencePreservesPublicReferencesAndMarksCyclesAsIncomplete(): void
    {
        $memory = new Memory();
        $value = Term::constant('public');
        $memory->cells['a'] = new Term('cell', 'b');
        $memory->cells['b'] = $value;
        self::assertSame($value, $memory->dereference(new Term('cell', 'a')));
        $memory->cells['b'] = new Term('cell', 'a');
        $cycle = $memory->dereference(new Term('cell', 'a'));
        self::assertSame('opaque', $cycle->kind);
        self::assertSame('CYCLIC_REFERENCE', $cycle->literal);
        self::assertSame(['type' => 'mixed','dependencyCoverage' => 'partial'], $cycle->attributes);
        self::assertFalse($cycle->secret);
        self::assertSame('uninitialized', $memory->dereference(new Term('cell', 'missing'))->kind);
    }

    public function testWriteTracksEveryVersionAndRetainsUnknownWriteDependencies(): void
    {
        $memory = new Memory();
        $location = new Location('new');
        $memory->write($location, Term::constant(1));
        $first = $memory->versions['new'];
        $memory->write($location, Term::constant(2));
        $second = $memory->versions['new'];
        $dependency = Term::constant('private', true);
        $memory->write(new Location('new', unknown:true), $dependency);
        self::assertSame(1, $first);
        self::assertSame(2, $second);
        self::assertSame(3, $memory->versions['new']);
        self::assertSame('UNKNOWN_WRITE', $memory->cells['new']->literal);
        self::assertSame([$dependency], $memory->cells['new']->operands);
        self::assertTrue($memory->cells['new']->isSecret());
    }

    public function testWritePathFollowsRootAliasesAndUpdatesTheirLiveArrayCursor(): void
    {
        $memory = new Memory();
        $memory->cells['alias'] = new Term('cell', 'array');
        $memory->cells['array'] = Term::fromNative([10,20]);
        $memory->liveArrays['cursor'] = new LiveArray(new Location('array'), [1], 0);
        $memory->writePath('alias', [0], Term::constant(30));
        $afterElement = $memory->liveArrays['cursor']->remaining;
        $memory->writePath('alias', [], Term::fromNative([40,50]));
        self::assertSame([1], $afterElement);
        self::assertSame([0,1], $memory->liveArrays['cursor']->remaining);
        self::assertSame([40,50], $memory->read(new Location('alias'))->native());
        self::assertSame('cell', $memory->cells['alias']->kind);
        self::assertSame('array', $memory->cells['alias']->literal);
    }

    public function testReplaceKeepsAReferenceWrapperAndReplacesTheReferencedArray(): void
    {
        $memory = new Memory();
        $memory->cells['array'] = Term::fromNative([10,20]);
        $memory->liveArrays['cursor'] = new LiveArray(new Location('array'), [1], 0);
        $reference = new Term('cell', 'array');
        $result = $memory->replace($reference, [], Term::fromNative([30,40]));
        self::assertSame($reference, $result);
        self::assertSame([30,40], $memory->cells['array']->native());
        self::assertSame([0,1], $memory->liveArrays['cursor']->remaining);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerReplacementBases')]
    public function testReplaceDistinguishesEmptyStorageFromUnknownExistingKeys(Term $before, bool $open): void
    {
        $result = (new Memory())->replace($before, ['nested','key'], Term::constant(7));
        self::assertSame('array', $result->kind);
        self::assertSame($open, $result->attributes['open']);
        self::assertSame(false, $result->operands['nested']->attributes['open']);
        self::assertSame(7, $result->operands['nested']->operands['key']->literal);
    }

    /**
     * @return iterable<string,array{Term,bool}>
     */
    public static function providerReplacementBases(): iterable
    {
        yield 'uninitialized' => [new Term('uninitialized'),false];
        yield 'null' => [Term::constant(null),false];
        yield 'unknown parameter' => [Term::parameter('x'),true];
        yield 'known closed array' => [Term::fromNative(['other' => 1]),false];
        yield 'open array' => [Term::array([], true),true];
    }

    public function testReferenceInitializesMissingStorageAndFollowsExistingRootAliases(): void
    {
        $memory = new Memory();
        $memory->cells['alias'] = new Term('cell', 'target');
        $root = $memory->reference(new Location('alias'));
        self::assertSame('target', $root);
        self::assertSame('constant', $memory->cells['target']->kind);
        self::assertNull($memory->cells['target']->literal);
        self::assertSame('target', $memory->cells['alias']->literal);
        self::assertSame('unseen', $memory->reference(new Location('unseen', unknown:true)));
        self::assertArrayNotHasKey('unseen', $memory->cells);
    }

    public function testReferenceTerminatesForCyclesAndDoesNotMistakeStringsForCellLinks(): void
    {
        $memory = new Memory();
        $memory->cells['a'] = new Term('cell', 'b');
        $memory->cells['b'] = new Term('cell', 'a');
        $memory->cells['text'] = Term::constant('a');
        self::assertSame('a', $memory->reference(new Location('a')));
        self::assertSame('text', $memory->reference(new Location('text')));
    }

    public function testReferenceMaterializesStringElementsWithoutMarkingPublicCellsSecret(): void
    {
        $memory = new Memory();
        $array = $memory->allocate(Term::fromNative(['key' => 'text']));
        $location = new Location($array->root, ['key']);
        $first = $memory->reference($location);
        $second = $memory->reference($location);
        self::assertSame($first, $second);
        self::assertSame('text', $memory->cells[$first]->literal);
        self::assertSame('constant', $memory->cells[$first]->kind);
        self::assertFalse($memory->cells[$first]->secret);
        self::assertSame('cell', $memory->raw($location)->kind);
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
        $memory->cells['a'] = new Term('array', operands:[0 => Term::constant(10),1 => Term::constant(20),2 => Term::constant(30)], attributes:['open' => true,'next' => 8], secret:true);
        $memory->liveArrays['cursor'] = new LiveArray(new Location('a'), [2], 1);
        $memory->remove(new Location('a', [1]));
        self::assertSame(['open' => true,'next' => null], $memory->cells['a']->attributes);
        self::assertTrue($memory->cells['a']->secret);
        self::assertSame([0,2], array_keys($memory->cells['a']->operands));
        self::assertSame([2], $memory->liveArrays['cursor']->remaining);
    }

    public function testSynchronizeChangesOnlyCursorsBoundToTheMutatedRoot(): void
    {
        $memory = new Memory();
        $before = Term::fromNative([1,2]);
        $after = Term::fromNative([3,4,5]);
        $cursor = new LiveArray(new Location('a'), [1], 0);
        $other = new LiveArray(new Location('b'), [1], 0);
        $memory->liveArrays = ['chosen' => $cursor,'other' => $other];
        $memory->synchronize('a', $before, $after, false);
        self::assertSame([1,2], $memory->liveArrays['chosen']->remaining);
        self::assertSame($other, $memory->liveArrays['other']);
        self::assertSame([1], $cursor->remaining);
    }

    public function testReadKeepsEveryKeyInAnUnresolvedNestedPath(): void
    {
        $memory = new Memory();
        $input = Term::parameter('record');
        $memory->cells['a'] = $input;
        $value = $memory->read(new Location('a', ['outer','inner']));
        self::assertSame('array-read', $value->kind);
        self::assertSame('inner', $value->operands[1]->literal);
        self::assertSame('array-read', $value->operands[0]->kind);
        self::assertSame('outer', $value->operands[0]->operands[1]->literal);
        self::assertSame($input, $value->operands[0]->operands[0]);
    }

    public function testReferenceMovesAGlobalValueIntoASharedCellBehindTheSlot(): void
    {
        $memory = new Memory();
        $memory->cells['global:x'] = Term::constant(1);
        $shared = $memory->reference(new Location('global:x'));
        self::assertNotSame('global:x', $shared);
        self::assertSame(['cell', $shared], [$memory->cells['global:x']->kind, $memory->cells['global:x']->literal]);
        self::assertSame($shared, $memory->reference(new Location('global:x')));
        $memory->cells['global:x'] = new Term('uninitialized');
        self::assertSame(1, $memory->read(new Location($shared))->native());
    }
}
