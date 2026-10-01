<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\NumericSize;

#[CoversClass(NumericSize::class)]
#[Medium]
final class NumericSizeTest extends TestCase
{
    public function testKeepsSignedScaleWithoutAssumingOneDialect(): void
    {
        $size = new NumericSize(2, -3);
        self::assertSame(-3, $size->scale);
        self::assertSame(5, (new NumericSize(2, 5))->scale);

    }

}
