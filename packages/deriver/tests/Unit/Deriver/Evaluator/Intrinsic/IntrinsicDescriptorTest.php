<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Intrinsic;

use Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntrinsicDescriptor::class)]
#[Small]
final class IntrinsicDescriptorTest extends TestCase
{
    public function testPreservesItsSemanticContract(): void
    {
        $descriptor = new IntrinsicDescriptor('example.compose', '2', 'compose', 2, [0, 1]);
        self::assertSame('example.compose', $descriptor->id);
        self::assertSame('2', $descriptor->version);
        self::assertSame([0, 1], $descriptor->dependencies);
    }
}
