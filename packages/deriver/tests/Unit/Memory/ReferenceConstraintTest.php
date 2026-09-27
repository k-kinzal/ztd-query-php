<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Deriver\Memory\Location;
use Deriver\Memory\Memory;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Memory\ReferenceConstraint
 */
#[CoversClass(ReferenceConstraint::class)]
#[UsesClass(Location::class)]
#[UsesClass(Memory::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ReferenceConstraintTest extends TestCase
{
    public function testFindDropsTheConstraintAfterThePropertyIsUnset(): void
    {
        $memory = new Memory();
        $property = new Location('object:one', ['id']);
        $memory->write($property, Term::constant(1));
        $memory->propertyTypes['object:one']['id'] = 'int';
        $reference = new Location($memory->reference($property));
        self::assertSame(['int'], (new ReferenceConstraint())->find($memory, $reference));
        $memory->remove($property);
        self::assertSame([], (new ReferenceConstraint())->find($memory, $reference));
    }
    public function testCellDoesNotAllocateStorageForAnOrdinaryElement(): void
    {
        $memory = new Memory();
        $location = $memory->allocate(Term::fromNative([1]));
        self::assertNull((new ReferenceConstraint())->cell($memory, new Location($location->root, [0])));
        self::assertCount(1, $memory->cells);
    }
    public function testFindIncludesTheDirectPropertyBeforeItsFirstReferenceEscape(): void
    {
        $memory = new Memory();
        $memory->cells['object:b'] = Term::array(['x' => Term::constant(1)]);
        $memory->propertyTypes['object:b']['x'] = 'int';
        self::assertSame(['int'], (new ReferenceConstraint())->find($memory, new Location('object:b', ['x'])));
        self::assertSame('constant', $memory->cells['object:b']->operands['x']->kind);
    }
}
