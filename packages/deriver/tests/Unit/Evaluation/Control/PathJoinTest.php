<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\PathJoin;
use Deriver\Evaluation\Offset\Address;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Lattice;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\JoinPaths;
use Tests\Fake\SolverFixture;

#[CoversClass(PathJoin::class)]
#[UsesClass(Address::class)]
#[UsesClass(Completion::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(Term::class)]
#[UsesClass(\Deriver\Reference\SourceRef::class)]
#[UsesClass(State::class)]
#[UsesClass(\Deriver\ControlFlow\CallableIdentity::class)]
#[UsesClass(\Deriver\Evaluation\Context::class)]
#[UsesClass(\Deriver\Evaluation\Control\Resources::class)]
#[UsesClass(\Deriver\Evaluation\Control\StateJoin::class)]
#[UsesClass(\Deriver\Evaluation\Havoc::class)]
#[UsesClass(\Deriver\Memory\Materialization::class)]
#[UsesClass(\Deriver\Memory\Memory::class)]
#[UsesClass(\Deriver\Model\Registration\Extensions::class)]
#[UsesClass(\Deriver\Model\Registration\Registry::class)]
#[UsesClass(\Deriver\Model\Registration\StateRegistry::class)]
#[UsesClass(\Deriver\Project\Configuration::class)]
#[UsesClass(\Deriver\Project\ProjectInput::class)]
#[UsesClass(\Deriver\Project\SourceFile::class)]
#[UsesClass(\Deriver\Project\SourceLimits::class)]
#[UsesClass(\Deriver\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Query\Budget::class)]
#[UsesClass(\Deriver\Query\QueryScope::class)]
#[UsesClass(\Deriver\Query\ResourceLimits::class)]
#[UsesClass(\Deriver\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Result\Frontier::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxCache::class)]
#[UsesClass(\Deriver\Source\Cache\SyntaxTree::class)]
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
#[UsesClass(\Deriver\Value\Identity::class)]
#[UsesClass(Lattice::class)]
#[UsesClass(\Deriver\Value\StringPrefix::class)]
#[Small]
final class PathJoinTest extends TestCase
{
    public function testJoinWidensDifferingPlainValuesAndKeepsSharedFacts(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant('SELECT a'), Term::constant('SELECT b'));
        $a->guard = ['shared' => true, 'left' => true];
        $b->guard = ['shared' => true, 'left' => false];
        $a->visits = [3 => 2];
        $b->visits = [3 => 5];
        $a->evidence = ['x'];
        $b->evidence = ['y'];
        $b->memory->sequence = 9;
        $joined = $join->join([$a, $b]);
        self::assertNotNull($joined);
        $value = $joined->memory->read($joined->locals['sql']);
        self::assertSame('SELECT ', $value->operands[0]->native());
        self::assertTrue((new Lattice())->contains($value, Term::constant('SELECT a')));
        self::assertTrue((new Lattice())->contains($value, Term::constant('SELECT b')));
        self::assertSame('kept', $joined->memory->read($joined->locals['table'])->native());
        self::assertSame(['shared' => true], $joined->guard);
        self::assertSame([3 => 5], $joined->visits);
        self::assertSame(['x', 'y'], $joined->evidence);
        self::assertSame(9, $joined->memory->sequence);
        self::assertSame([], $joined->constraints);
        self::assertSame('normal', $joined->completion->kind);
        self::assertSame('SELECT a', $a->memory->read($a->locals['sql'])->native());
    }

    public function testJoinRejectsDifferentPredecessors(): void
    {
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(2));
        $b->previous = 4;
        self::assertNull((new PathJoin(SolverFixture::context()))->join([$a, $b]));
    }

    public function testJoinRejectsDifferentAliasesAndObjects(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant(1), new Term('cell', 'cell:9'));
        self::assertNull($join->join([$a, $b]));
        [$a, $b] = JoinPaths::pair(new Term('object', 'object:1', attributes: ['class' => 'A']), new Term('object', 'object:2', attributes: ['class' => 'A']));
        self::assertNull($join->join([$a, $b]));
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(2));
        $b->locals['other'] = new Location('other');
        self::assertNull($join->join([$a, $b]));
    }

    public function testJoinKeepsRegisterMetadataOnlyWhenEveryPathAgrees(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(2));
        $a->registers = ['r1' => Term::constant(1), 'r2' => Term::constant('only a')];
        $b->registers = ['r1' => Term::constant(1)];
        $a->addresses = ['r1' => new Location('x'), 'r2' => new Location('y')];
        $b->addresses = ['r1' => new Location('x')];
        $joined = $join->join([$a, $b]);
        self::assertNotNull($joined);
        self::assertSame(['r1'], array_keys($joined->registers));
        self::assertSame(['r1'], array_keys($joined->addresses));
        $b->addresses = ['r1' => new Location('z')];
        self::assertNull($join->join([$a, $b]));
    }

    public function testJoinWidensCompletedReturnValuesWithoutComparingRegisters(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(1));
        $a->completion = new Completion('return', Term::constant('a'));
        $b->completion = new Completion('return', Term::constant(2));
        $a->block = 1;
        $b->block = 2;
        $a->registers = ['r1' => new Term('cell', 'cell:1')];
        $b->registers = ['r1' => Term::constant(1)];
        self::assertNull($join->join([$a, $b]));
        $joined = $join->join([$a, $b], true);
        self::assertNotNull($joined);
        self::assertSame('return', $joined->completion->kind);
        self::assertSame('int|string', (new Lattice())->type($joined->completion->value ?? Term::constant(null)));
    }

    public function testMergeAccumulatesBookkeepingConservatively(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(2));
        $a->observed = true;
        $b->observed = false;
        $a->producers = ['r1' => 'x', 'r2' => 'y'];
        $b->producers = ['r1' => 'x', 'r2' => 'z'];
        $a->registers = ['r1' => Term::constant(1), 'r2' => Term::constant(2)];
        $b->unknownLocals = 'EVAL';
        $b->memory->unknownShared = 'MISSING_CALL_MODEL';
        $b->memory->versions = ['cell:9' => 4];
        $join->merge($a, $b);
        self::assertFalse($a->observed);
        self::assertSame(['r1' => 'x'], $a->producers);
        self::assertSame('EVAL', $a->unknownLocals);
        self::assertSame('MISSING_CALL_MODEL', $a->memory->unknownShared);
        self::assertSame(4, $a->memory->versions['cell:9']);
    }

    public function testStructureIgnoresValuesButNotPositionsOrAliases(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(2));
        $b->guard = ['g' => true];
        $b->registers = ['r1' => Term::constant(3)];
        self::assertSame($join->structure($a), $join->structure($b));
        $b->block = 3;
        self::assertNotSame($join->structure($a), $join->structure($b));
        self::assertSame($join->structure($a, true), $join->structure($b, true));
        $b->memory->classes['object:1'] = 'A';
        self::assertNotSame($join->structure($a, true), $join->structure($b, true));
    }

    public function testCommonKeepsEqualMetadataPresentOnEveryPath(): void
    {
        $join = new PathJoin(SolverFixture::context());
        self::assertEquals(['r1' => new Location('x')], $join->common([['r1' => new Location('x'), 'r2' => new Location('y')], ['r1' => new Location('x')]]));
        self::assertNull($join->common([['r1' => new Location('x')], ['r1' => new Location('y')]]));
    }

    public function testValuesWidensDifferencesAndRejectsIdentities(): void
    {
        $join = new PathJoin(SolverFixture::context());
        $values = $join->values([['a' => Term::constant(1), 'b' => Term::constant('x')], ['a' => Term::constant(1), 'b' => Term::constant('y')]], false);
        self::assertNotNull($values);
        self::assertSame(1, $values['a']->native());
        self::assertSame('WIDENED', $values['b']->literal);
        self::assertSame(['a'], array_keys($join->values([['a' => Term::constant(1), 'b' => Term::constant(2)], ['a' => Term::constant(1)]], false) ?? []));
        self::assertSame(['a', 'b'], array_keys($join->values([['a' => Term::constant(1), 'b' => Term::constant(2)], ['a' => Term::constant(1)]], true) ?? []));
        self::assertNull($join->values([['a' => new Term('cell', 'cell:1')], ['a' => new Term('cell', 'cell:2')]], false));
    }

    public function testLocationsGeneralizeDifferingAddressesIntoOneRoot(): void
    {
        $join = new PathJoin(SolverFixture::context());
        $locations = $join->locations([['r1' => new Location('cell:1', [0]), 'r2' => new Location('cell:2')], ['r1' => new Location('cell:1', [1]), 'r2' => new Location('cell:2')]]);
        self::assertNotNull($locations);
        self::assertEquals(new Location('cell:1', unknown: true), $locations['r1']);
        self::assertEquals(new Location('cell:2'), $locations['r2']);
        self::assertNull($join->locations([['r1' => new Location('cell:1')], ['r1' => new Location('cell:2')]]));
    }

    public function testOffsetsWidenKeysBelowOneParent(): void
    {
        $join = new PathJoin(SolverFixture::context());
        $offsets = $join->offsets([['r1' => new Address('r0', Term::constant(0))], ['r1' => new Address('r0', Term::constant(1))]]);
        self::assertNotNull($offsets);
        self::assertSame('r0', $offsets['r1']->parent);
        self::assertTrue((new Lattice())->contains($offsets['r1']->key ?? Term::constant(null), Term::constant(1)));
        self::assertNull($join->offsets([['r1' => new Address('r0', null)], ['r1' => new Address('r0', Term::constant(1))]]));
        self::assertNull($join->offsets([['r1' => new Address('r0', null)], ['r1' => new Address('r9', null)]]));
    }

    public function testLoopsKeepOnlyBookkeepingBothPathsShare(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = JoinPaths::pair(Term::constant(1), Term::constant(2));
        $a->loopGuards = [5 => ['flag' => true, 'other' => true], 6 => ['x' => true]];
        $b->loopGuards = [5 => ['flag' => false, 'other' => true]];
        $a->approximations = [5 => ['cell:1' => Term::constant(1)]];
        $b->approximations = [5 => ['cell:1' => Term::constant(2)]];
        $a->stableHeader = 5;
        $join->loops($a, $b);
        self::assertSame([5 => ['other' => true]], $a->loopGuards);
        self::assertSame([], $a->approximations);
        self::assertNull($a->stableHeader);
    }

    public function testValuesKeepObjectRecordsExact(): void
    {
        $join = new PathJoin(SolverFixture::context());
        self::assertNull($join->values([['object:1' => Term::array([])], ['object:1' => Term::array(['p' => Term::constant(5)])]], true, true));
        self::assertNotNull($join->values([['cell:1' => Term::array([])], ['cell:1' => Term::array(['p' => Term::constant(5)])]], true, true));
        self::assertNotNull($join->values([['object:1' => Term::array([])], ['object:1' => Term::array([])]], true, true));
    }

    public function testPlainRejectsNestedIdentities(): void
    {
        $join = new PathJoin(SolverFixture::context());
        self::assertTrue($join->plain(Term::array([Term::constant(1), Term::parameter('x')])));
        self::assertFalse($join->plain(Term::array([Term::constant(1), new Term('cell', 'cell:1')])));
        self::assertTrue($join->plain(new Term('uninitialized')));
    }
}
