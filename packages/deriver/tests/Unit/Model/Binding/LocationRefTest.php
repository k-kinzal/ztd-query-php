<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Binding\LocationRef
 */
#[CoversClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Plan\Expression::class)]
#[Small]
final class LocationRefTest extends TestCase
{
    public function testParameterNamesFormalStorage(): void
    {
        self::assertSame('value', \Deriver\Model\Binding\LocationRef::parameter('value')->name);
    }
    public function testStateDefaultsToBoundReceiver(): void
    {
        self::assertSame('this', \Deriver\Model\Binding\LocationRef::state('domain.slot')->receiver?->name);
    }
    public function testElementRetainsItsContainingLocation(): void
    {
        $parent = \Deriver\Model\Binding\LocationRef::parameter('items');
        self::assertSame($parent, \Deriver\Model\Binding\LocationRef::element($parent)->parent);
    }
}
