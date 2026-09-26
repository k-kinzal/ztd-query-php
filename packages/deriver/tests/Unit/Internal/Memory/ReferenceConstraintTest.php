<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Memory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Memory\ReferenceConstraint
 */
#[CoversClass(\Deriver\Internal\Memory\ReferenceConstraint::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Internal\Value\Arrays::class)]
#[UsesClass(\Deriver\Internal\Value\PhpSemantics::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ReferenceConstraintTest extends TestCase
{
    public function testFindDropsTheConstraintAfterThePropertyIsUnset(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $property = new \Deriver\Internal\Memory\Location('object:one', ['id']);
        $memory->write($property, \Deriver\Value\Term::constant(1));
        $memory->propertyTypes['object:one']['id'] = 'int';
        $reference = new \Deriver\Internal\Memory\Location($memory->reference($property));
        self::assertSame(['int'], (new \Deriver\Internal\Memory\ReferenceConstraint())->find($memory, $reference));
        $memory->remove($property);
        self::assertSame([], (new \Deriver\Internal\Memory\ReferenceConstraint())->find($memory, $reference));
    }
    public function testCellDoesNotAllocateStorageForAnOrdinaryElement(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $location = $memory->allocate(\Deriver\Value\Term::fromNative([1]));
        self::assertNull((new \Deriver\Internal\Memory\ReferenceConstraint())->cell($memory, new \Deriver\Internal\Memory\Location($location->root, [0])));
        self::assertCount(1, $memory->cells);
    }
    public function testFindIncludesTheDirectPropertyBeforeItsFirstReferenceEscape(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $memory->cells['object:b'] = \Deriver\Value\Term::array(['x' => \Deriver\Value\Term::constant(1)]);
        $memory->propertyTypes['object:b']['x'] = 'int';
        self::assertSame(['int'], (new \Deriver\Internal\Memory\ReferenceConstraint())->find($memory, new \Deriver\Internal\Memory\Location('object:b', ['x'])));
        self::assertSame('constant', $memory->cells['object:b']->operands['x']->kind);
    }
}
