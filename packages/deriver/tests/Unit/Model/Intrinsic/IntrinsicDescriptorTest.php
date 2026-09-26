<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Deriver\Model\Intrinsic\IntrinsicDescriptor::class)]
#[Small]
final class IntrinsicDescriptorTest extends TestCase
{
    public function testPreservesItsSemanticContract(): void
    {
        $descriptor = new \Deriver\Model\Intrinsic\IntrinsicDescriptor('example.compose', '2', 'compose', 2, [0, 1]);
        self::assertSame('example.compose', $descriptor->id);
        self::assertSame('2', $descriptor->version);
        self::assertSame([0, 1], $descriptor->dependencies);
    }
}
