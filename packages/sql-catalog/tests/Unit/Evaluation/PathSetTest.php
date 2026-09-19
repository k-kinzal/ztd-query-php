<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PathSet;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(PathSet::class)]
#[UsesClass(Domain::class)]
#[UsesClass(Environment::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class PathSetTest extends TestCase
{
    public function testAnEmptySetStillHoldsOnePath(): void
    {
        self::assertCount(1, (new PathSet())->environments());
    }

    public function testOfHoldsTheGivenPath(): void
    {
        $environment = new Environment(['a' => Domain::literal('x')]);
        self::assertSame([$environment], PathSet::of($environment)->environments());
    }

    public function testEnvironmentsAreThePathsBeingKeptApart(): void
    {
        $first = new Environment(['a' => Domain::literal('x')]);
        $second = new Environment(['a' => Domain::literal('y')]);

        self::assertSame([$first, $second], (new PathSet([$first, $second]))->environments());
    }

    public function testCountIsTheNumberOfPathsKeptApart(): void
    {
        self::assertSame(2, (new PathSet([new Environment(), new Environment()]))->count());
    }

    public function testIsJoinedOnlyAfterPathsWereMergedAway(): void
    {
        self::assertFalse((new PathSet())->isJoined());
        self::assertTrue((new PathSet([new Environment()], true))->isJoined());
    }

    public function testForkIsIndependentOfTheOriginal(): void
    {
        $paths = PathSet::of(new Environment(['a' => Domain::literal('x')]));
        $fork = $paths->fork();
        $fork->environments()[0]->write('a', Domain::literal('y'));

        self::assertSame('x', $paths->environments()[0]->read('a')->soleLiteral()?->value);
    }

    public function testMergeKeepsThePathsOfBothSides(): void
    {
        $left = PathSet::of(new Environment(['a' => Domain::literal('x')]));
        $right = PathSet::of(new Environment(['a' => Domain::literal('y')]));

        self::assertSame(2, $left->merge($right)->count());
    }

    public function testMergeCarriesOverThatPathsWereJoined(): void
    {
        $left = new PathSet([new Environment()], true);
        self::assertTrue($left->merge(new PathSet())->isJoined());
    }

    public function testBoundedJoinsPathsPastTheBound(): void
    {
        $environments = array_map(
            static fn (int $index): Environment => new Environment(['a' => Domain::literal($index)]),
            range(0, PathSet::MAX_PATHS),
        );
        $bounded = (new PathSet($environments))->bounded();

        self::assertSame(1, $bounded->count());
        self::assertTrue($bounded->isJoined());
    }

    public function testBoundedLeavesPathsWithinTheBoundAlone(): void
    {
        $paths = new PathSet([new Environment(), new Environment()]);
        self::assertSame($paths, $paths->bounded());
    }

    public function testJoinReportsWhatAnyPathMayHold(): void
    {
        $paths = new PathSet([
            new Environment(['a' => Domain::literal('x')]),
            new Environment(['a' => Domain::literal('y')]),
        ]);

        self::assertCount(2, $paths->join()->read('a')->terms);
    }

    public function testBecomeFromTakesOnAnotherSetInPlace(): void
    {
        $paths = PathSet::of(new Environment(['a' => Domain::literal('x')]));
        $paths->becomeFrom(new PathSet([new Environment(), new Environment()], true));

        self::assertSame(2, $paths->count());
        self::assertTrue($paths->isJoined());
    }
}
