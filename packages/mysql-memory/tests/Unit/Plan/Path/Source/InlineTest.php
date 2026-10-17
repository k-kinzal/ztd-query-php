<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Inline::class)]
#[Small]
final class InlineTest extends TestCase
{
    public function testWidthIsTheDeclaredNumberOfValues(): void
    {
        self::assertSame(2, (new Inline([[new Constant(Domain::integer(), 1), new Constant(Domain::integer(), 2)]], 2))->width());
    }

    public function testWidthStaysDeclaredForNoRows(): void
    {
        self::assertSame(3, (new Inline([], 3))->width());
    }
}
