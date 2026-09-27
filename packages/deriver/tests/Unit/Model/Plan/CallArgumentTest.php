<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Plan\CallArgument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Plan\CallArgument
 */
#[CoversClass(CallArgument::class)]
#[UsesClass(LocationRef::class)]
#[Small]
final class CallArgumentTest extends TestCase
{
    public function testNamedReferenceMetadataIsRetained(): void
    {
        $location = LocationRef::parameter('item');
        $argument = new CallArgument($location, 'value');
        self::assertSame($location, $argument->value);
        self::assertSame('value', $argument->name);
        self::assertFalse($argument->unpack);
    }
}
