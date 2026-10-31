<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Source\Span;

#[CoversClass(Span::class)]
#[Small]
final class SpanTest extends TestCase
{
    public function testRetainsAByteRangeWithoutSourceText(): void
    {
        $span = new Span(12, 4);
        self::assertSame(['offset' => 12, 'length' => 4], get_object_vars($span));
    }

    public function testRejectsANegativeOffset(): void
    {
        $this->expectExceptionMessage('A source span has a nonnegative offset and length.');
        new Span(-1, 1);
    }

    public function testRejectsANegativeLength(): void
    {
        $this->expectExceptionMessage('A source span has a nonnegative offset and length.');
        new Span(0, -1);
    }

    public function testRejectsAnOverflowingEnd(): void
    {
        $this->expectExceptionMessage('The end of a source span fits an integer.');
        new Span(PHP_INT_MAX, 1);
    }
}
