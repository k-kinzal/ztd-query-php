<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Scalar\Value\Literal::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class LiteralTest extends TestCase
{
    public function testRetainsTheNewValueIndependentlyOfItsDiagnosticSource(): void
    {
        $original = Expression::literal(1, Dialect::PostgreSql);
        $changed = new \SqlSemantics\Model\Scalar\Value\Literal($original->facts, $original->source, \SqlSemantics\Model\Scalar\Value\LiteralKind::Number, '2');
        self::assertSame('2', $changed->structure()->toString());
        self::assertSame('1', $original->spelling());
    }

    public function testRejectsAnExpressionDisguisedAsALiteral(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Scalar\Value\Literal($value->facts, $value->source, \SqlSemantics\Model\Scalar\Value\LiteralKind::Number, '1 + 2');
    }

    public function testClassifiesSqliteNumericSeparators(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1_024');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('1_024', $query->outputs[0]->expression->spelling());
        self::assertSame('SELECT 1_024', $query->toString());
    }

    public function testInputsHasNoOperands(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertSame([], $value->inputs());
        self::assertSame([], $value->lineage());
    }

    public function testSpellingReturnsTheEncodedText(): void
    {
        self::assertSame("'it''s'", Expression::literal("it's", Dialect::PostgreSql)->spelling());
        self::assertSame('NULL', Expression::literal(null, Dialect::Sqlite)->spelling());
    }

    public function testWithFactsKeepsTheKindAndText(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $value);
        $copy = $value->withFacts(new \SqlSemantics\Model\Scalar\ExpressionFacts($value->type, \SqlSemantics\Type\Nullability::MaybeNull));
        self::assertNotSame($value, $copy);
        self::assertSame(\SqlSemantics\Model\Scalar\Value\LiteralKind::Number, $copy->literalKind);
        self::assertSame('1', $copy->text);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $copy->nullability);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $value->nullability);
    }
}
