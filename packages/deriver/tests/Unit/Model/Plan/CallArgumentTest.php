<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Plan\CallArgument
 */
#[CoversClass(\Deriver\Model\Plan\CallArgument::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[Small]
final class CallArgumentTest extends TestCase
{
    public function testNamedReferenceMetadataIsRetained(): void
    {
        $location = \Deriver\Model\Binding\LocationRef::parameter('item');
        $argument = new \Deriver\Model\Plan\CallArgument($location, 'value');
        self::assertSame($location, $argument->value);
        self::assertSame('value', $argument->name);
        self::assertFalse($argument->unpack);
    }
}
