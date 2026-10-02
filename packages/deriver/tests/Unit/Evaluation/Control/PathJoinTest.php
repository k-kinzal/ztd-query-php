<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\PathJoin;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Lattice;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SolverFixture;

#[CoversClass(PathJoin::class)]
#[UsesClass(Completion::class)]
#[UsesClass(State::class)]
#[UsesClass(Location::class)]
#[UsesClass(Term::class)]
#[UsesClass(SourceRef::class)]
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
        [$a, $b] = $this->paths(Term::constant('SELECT a'), Term::constant('SELECT b'));
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
        [$a, $b] = $this->paths(Term::constant(1), Term::constant(2));
        $b->previous = 4;
        self::assertNull((new PathJoin(SolverFixture::context()))->join([$a, $b]));
    }

    public function testJoinRejectsDifferentAliasesAndObjects(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = $this->paths(Term::constant(1), new Term('cell', 'cell:9'));
        self::assertNull($join->join([$a, $b]));
        [$a, $b] = $this->paths(new Term('object', 'object:1', attributes: ['class' => 'A']), new Term('object', 'object:2', attributes: ['class' => 'A']));
        self::assertNull($join->join([$a, $b]));
        [$a, $b] = $this->paths(Term::constant(1), Term::constant(2));
        $b->locals['other'] = new Location('other');
        self::assertNull($join->join([$a, $b]));
    }

    public function testJoinKeepsRegisterMetadataOnlyWhenEveryPathAgrees(): void
    {
        $join = new PathJoin(SolverFixture::context());
        [$a, $b] = $this->paths(Term::constant(1), Term::constant(2));
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
        [$a, $b] = $this->paths(Term::constant(1), Term::constant(1));
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

    public function testPlainRejectsNestedIdentities(): void
    {
        $join = new PathJoin(SolverFixture::context());
        self::assertTrue($join->plain(Term::array([Term::constant(1), Term::parameter('x')])));
        self::assertFalse($join->plain(Term::array([Term::constant(1), new Term('cell', 'cell:1')])));
        self::assertTrue($join->plain(new Term('uninitialized')));
    }

    /**
     * Builds two paths at one block that differ only in the value of one local.
     * @param Term $first Value on the first path
     * @param Term $second Value on the second path
     * @return array{State, State} Joinable candidates
     */
    private function paths(Term $first, Term $second): array
    {
        $base = new State();
        $base->block = 2;
        $base->previous = 1;
        $base->memory->write($base->local('table'), Term::constant('kept'));
        $base->local('sql');
        $a = $base->fork();
        $b = $base->fork();
        $a->memory->write($a->local('sql'), $first);
        $b->memory->write($b->local('sql'), $second);
        return [$a, $b];
    }
}
