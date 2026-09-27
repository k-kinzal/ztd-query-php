<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Binding;

use Deriver\Model\Binding\LocationRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Binding\LocationRef
 */
#[CoversClass(LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[Small]
final class LocationRefTest extends TestCase
{
    public function testParameterNamesFormalStorage(): void
    {
        self::assertSame('value', LocationRef::parameter('value')->name);
    }
    public function testStateDefaultsToBoundReceiver(): void
    {
        self::assertSame('this', LocationRef::state('domain.slot')->receiver?->name);
    }
    public function testElementRetainsItsContainingLocation(): void
    {
        $parent = LocationRef::parameter('items');
        self::assertSame($parent, LocationRef::element($parent)->parent);
    }
}
