<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\FieldLayout;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(FieldLayout::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class FieldLayoutTest extends TestCase
{
    public function testEmptyIsTrueOnlyWithoutSeparators(): void
    {
        $comma = Expression::literal(',', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $comma);
        self::assertTrue((new FieldLayout())->empty());
        self::assertFalse((new FieldLayout($comma))->empty());
    }

    public function testRejectsAMultiByteEnclosure(): void
    {
        $quotes = Expression::literal('""', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $quotes);
        $this->expectException(InvalidStructure::class);
        new FieldLayout(null, $quotes);
    }

    public function testRejectsOptionallyWithoutAnEnclosure(): void
    {
        $this->expectException(InvalidStructure::class);
        new FieldLayout(null, null, true);
    }
}
