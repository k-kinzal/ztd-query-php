<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Expression;
use SqlSemantics\Statement\Source\Span;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Expression::class)]
#[Small]
final class ExpressionTest extends TestCase
{
    public function testDataTypeKeepsMissingInputsExplicit(): void
    {
        $type = (new StringLiteral(['42']))->dataType();
        self::assertInstanceOf(Dependent::class, $type);
        self::assertCount(1, $type->missing);
    }

    public function testNullabilityDescribesTheValueBeforeStorageConversion(): void
    {
        self::assertSame(Nullability::NotNull, (new StringLiteral(['42']))->nullability());
    }

    public function testSourceIsIndependentOfTheExpressionValue(): void
    {
        $source = new Span(12, 4);
        $generated = new StringLiteral(['42']);
        $located = new StringLiteral(['42'], location: $source);
        self::assertSame($generated->value(), $located->value());
        self::assertNull($generated->source());
        self::assertSame($source, $located->source());
    }
}
