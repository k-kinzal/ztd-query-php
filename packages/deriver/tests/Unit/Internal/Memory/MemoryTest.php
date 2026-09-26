<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Internal\Memory\Memory::class)]
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
#[UsesClass(\Deriver\Internal\IR\Terminator::class)]
#[UsesClass(\Deriver\Internal\Memory\LiveArray::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Materialization::class)]
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
#[UsesClass(\Deriver\Internal\Solver\Offset\Address::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Path::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Reader::class)]
#[UsesClass(\Deriver\Internal\Solver\Offset\Transfer::class)]
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
        $memory = new \Deriver\Internal\Memory\Memory();
        $a = $memory->allocate(\Deriver\Value\Term::constant(1));
        $b = $memory->allocate(\Deriver\Value\Term::constant(1));
        $memory->write($b, \Deriver\Value\Term::constant(2));
        self::assertNotSame($a->root, $b->root);
        self::assertSame(1, $memory->read($a)->native());
        self::assertSame(2, $memory->read($b)->native());
    }

    public function testReadDistinguishesUninitializedFromNull(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $null = $memory->allocate(\Deriver\Value\Term::constant(null));
        self::assertSame('uninitialized', $memory->read(new \Deriver\Internal\Memory\Location('missing'))->kind);
        self::assertSame('constant', $memory->read($null)->kind);
    }

    public function testDereferenceStopsAtReferenceCycles(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells['a'] = new \Deriver\Value\Term('cell', 'b');
        $memory->cells['b'] = new \Deriver\Value\Term('cell', 'a');
        self::assertSame('CYCLIC_REFERENCE', $memory->dereference(new \Deriver\Value\Term('cell', 'a'))->literal);
    }

    public function testWritePreservesUnrelatedArrayFields(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $a = $memory->allocate(\Deriver\Value\Term::fromNative(['a' => 1, 'b' => 2]));
        $memory->write(new \Deriver\Internal\Memory\Location($a->root, ['a']), \Deriver\Value\Term::constant(3));
        self::assertSame(['a' => 3, 'b' => 2], $memory->read($a)->native());
        self::assertSame(1, $memory->versions[$a->root]);
    }

    public function testWritePathRebindsOnlyTheFinalReferenceSlot(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $a = $memory->allocate(\Deriver\Value\Term::constant(1));
        $b = $memory->allocate(\Deriver\Value\Term::constant(2));
        $record = $memory->allocate(\Deriver\Value\Term::array([new \Deriver\Value\Term('cell', $a->root)]));
        $memory->writePath($record->root, [0], new \Deriver\Value\Term('cell', $b->root), true);
        $memory->write(new \Deriver\Internal\Memory\Location($record->root, [0]), \Deriver\Value\Term::constant(3));
        self::assertSame(1, $memory->read($a)->native());
        self::assertSame(3, $memory->read($b)->native());
    }

    public function testReplacePreservesNestedStructure(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $result = $memory->replace(\Deriver\Value\Term::fromNative(['a' => ['x' => 1, 'y' => 2]]), ['a', 'x'], \Deriver\Value\Term::constant(3));
        self::assertSame(['a' => ['x' => 3, 'y' => 2]], $result->native());
    }

    public function testReferenceMaterializesOneSharedCell(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $array = $memory->allocate(\Deriver\Value\Term::fromNative([1]));
        $element = new \Deriver\Internal\Memory\Location($array->root, [0]);
        $first = $memory->reference($element);
        $second = $memory->reference($element);
        self::assertSame($first, $second);
        $memory->write(new \Deriver\Internal\Memory\Location($first), \Deriver\Value\Term::constant(2));
        self::assertSame(2, $memory->read($element)->native());
    }

    public function testRawKeepsTheReferenceIdentity(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $cell = $memory->allocate(\Deriver\Value\Term::constant(1));
        $array = $memory->allocate(\Deriver\Value\Term::array([new \Deriver\Value\Term('cell', $cell->root)]));
        self::assertSame('cell', $memory->raw(new \Deriver\Internal\Memory\Location($array->root, [0]))->kind);
        self::assertSame(1, $memory->read(new \Deriver\Internal\Memory\Location($array->root, [0]))->native());
    }

    public function testRemoveKeepsTheNextAppendIndex(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $array = $memory->allocate(\Deriver\Value\Term::fromNative([5 => 'a']));
        $memory->remove(new \Deriver\Internal\Memory\Location($array->root, [5]));
        self::assertSame(6, (new \Deriver\Internal\Value\Arrays())->next($memory->read($array)));
        self::assertSame([], $memory->read($array)->native());
    }

    public function testMaterializeReadsReferencedArrayElements(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $cell = $memory->allocate(\Deriver\Value\Term::constant('shared'));
        self::assertSame(['shared'], $memory->materialize(\Deriver\Value\Term::array([new \Deriver\Value\Term('cell', $cell->root)]))->native());
    }

    public function testSynchronizeKeepsLiveTraversalThroughElementDeletion(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells['a'] = \Deriver\Value\Term::fromNative([10,20,30]);
        $memory->liveArrays['i'] = new \Deriver\Internal\Memory\LiveArray(new \Deriver\Internal\Memory\Location('a'), [1,2], 0);
        $memory->remove(new \Deriver\Internal\Memory\Location('a', [0]));
        self::assertSame([1,2], $memory->liveArrays['i']->remaining);
        $memory->write(new \Deriver\Internal\Memory\Location('a'), \Deriver\Value\Term::fromNative([40,50]));
        self::assertSame([0,1], $memory->liveArrays['i']->remaining);
    }

    public function testRemovePreservesAnExplicitSecretLabelOnTheAggregate(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $value = new \Deriver\Value\Term('array', operands:['private' => \Deriver\Value\Term::constant('derived'),'drop' => \Deriver\Value\Term::constant(1)], attributes:['open' => false], secret:true);
        $location = $memory->allocate($value);
        $memory->remove(new \Deriver\Internal\Memory\Location($location->root, ['drop']));
        $remaining = $memory->read($location);
        self::assertSame(['private' => 'derived'], $remaining->native());
        self::assertTrue($remaining->isSecret());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerElementConfidentiality')]
    public function testElementPreservesContainerKeyAndSelectedValueConfidentiality(bool $containerSecret, bool $keySecret, bool $childSecret, bool $expected): void
    {
        $child = \Deriver\Value\Term::constant('private-value', $childSecret);
        $array = new \Deriver\Value\Term('array', operands: ['chosen' => $child], attributes: ['open' => false], secret: $containerSecret);
        $result = (new \Deriver\Internal\Memory\Memory())->element($array, 'chosen', $keySecret);
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
        $memory = new \Deriver\Internal\Memory\Memory();
        $object = new \Deriver\Value\Term('object', 'one', attributes: ['class' => 'Box']);
        $location = $memory->allocate($object);
        $array = new \Deriver\Value\Term('array', operands: ['object' => new \Deriver\Value\Term('cell', $location->root)], attributes:['open' => false], secret:true);
        $entry = $memory->element($array, 'object');
        self::assertSame('one', $entry->literal);
        self::assertSame(['class' => 'Box'], $entry->attributes);
        self::assertTrue($entry->secret);
        self::assertSame('uninitialized', $memory->element($array, 'missing')->kind);
        self::assertTrue($memory->element($array, 'missing')->secret);
        self::assertSame('UNKNOWN_ARRAY_KEY', $memory->element(\Deriver\Value\Term::array([], true), 'missing')->literal);
        self::assertSame($object, $memory->read($location));
    }
    public function testReadCarriesAnOuterSecretLabelThroughNestedArrays(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $array = new \Deriver\Value\Term('array', operands:['outer' => \Deriver\Value\Term::fromNative(['inner' => 'private-value'])], attributes:['open' => false], secret:true);
        $location = $memory->allocate($array);
        $result = $memory->read(new \Deriver\Internal\Memory\Location($location->root, ['outer','inner']));
        self::assertSame('private-value', $result->native());
        self::assertTrue($result->isSecret());
        self::assertSame($array, $memory->read($location));
    }
    public function testDereferenceRetainsSecretLabelsAcrossCellChainsAndCycles(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells['first'] = new \Deriver\Value\Term('cell', 'second', secret:true);
        $memory->cells['second'] = \Deriver\Value\Term::constant('private-value');
        $value = $memory->dereference(new \Deriver\Value\Term('cell', 'first'));
        $memory->cells['second'] = new \Deriver\Value\Term('cell', 'first');
        $cycle = $memory->dereference(new \Deriver\Value\Term('cell', 'first'));
        self::assertTrue($value->secret);
        self::assertSame('private-value', $value->native());
        self::assertSame('CYCLIC_REFERENCE', $cycle->literal);
        self::assertTrue($cycle->secret);
    }
    public function testReferenceRetainsConfidentialityWhenAnExistingArrayCellEscapes(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $value = $memory->allocate(\Deriver\Value\Term::constant('private-value'));
        $link = $memory->allocate(new \Deriver\Value\Term('cell', $value->root));
        $array = $memory->allocate(new \Deriver\Value\Term('array', operands:['value' => new \Deriver\Value\Term('cell', $link->root)], attributes:['open' => false], secret:true));
        $reference = $memory->reference(new \Deriver\Internal\Memory\Location($array->root, ['value']));
        self::assertSame($link->root, $reference);
        self::assertSame('private-value', $memory->read(new \Deriver\Internal\Memory\Location($reference))->native());
        self::assertTrue($memory->read(new \Deriver\Internal\Memory\Location($reference))->secret);
        $memory->write($value, \Deriver\Value\Term::constant('updated'));
        self::assertSame('updated', $memory->read(new \Deriver\Internal\Memory\Location($reference))->native());
        self::assertTrue($memory->read(new \Deriver\Internal\Memory\Location($reference))->secret);
    }

}
