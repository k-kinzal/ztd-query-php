<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\LineLayout;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(LineLayout::class)]
final class LineLayoutTest extends TestCase
{
    public function testKeepsMultiByteSeparators(): void
    {
        $terminator = Expression::literal("\r\n", Dialect::MySql);
        self::assertInstanceOf(Literal::class, $terminator);
        self::assertSame($terminator, (new LineLayout($terminator))->terminator);
    }

    public function testRejectsANumericSeparator(): void
    {
        $number = Expression::literal(5, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new LineLayout(null, $number);
    }
}
