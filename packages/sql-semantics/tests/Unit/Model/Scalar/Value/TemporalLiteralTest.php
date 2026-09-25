<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Scalar\Value\TemporalLiteral;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(TemporalLiteral::class)]
#[Medium]
final class TemporalLiteralTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "DATE '2024-01-02'", LiteralKind::Date, 'date', "DATE '2024-01-02'"])]
    #[TestWith(['mysql-5.7.44', "{t '03:04:05'}", LiteralKind::Time, 'time', "TIME '03:04:05'"])]
    #[TestWith(['mysql-8.0.44', "{TS '2024-01-02 03:04:05'}", LiteralKind::Timestamp, 'datetime', "TIMESTAMP '2024-01-02 03:04:05'"])]
    #[TestWith(['mysql-8.4.7', "TIMESTAMP '2024-01-02 03:04:05'", LiteralKind::Timestamp, 'datetime', "TIMESTAMP '2024-01-02 03:04:05'"])]
    #[TestWith(['mysql-9.1.0', "{d '2024-01-02'}", LiteralKind::Date, 'date', "DATE '2024-01-02'"])]
    public function testSpellingKeepsTheCategoryAndTheString(string $version, string $sql, LiteralKind $category, string $type, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT ' . $sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $literal = $query->outputs[0]->expression;
        self::assertInstanceOf(TemporalLiteral::class, $literal);
        self::assertSame($category, $literal->category);
        self::assertSame($type, $literal->type->name);
        self::assertSame(Nullability::NotNull, $literal->nullability);
        self::assertSame([], $literal->inputs());
        self::assertSame($expected, $literal->spelling());
        self::assertSame('SELECT ' . $expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testSpellingRejectsANonTemporalCategory(): void
    {
        $text = Expression::literal('2024-01-02', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        $this->expectException(InvalidStructure::class);
        new TemporalLiteral($text->source, LiteralKind::Number, $text);
    }

    public function testSpellingRejectsANumberString(): void
    {
        $number = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new TemporalLiteral($number->source, LiteralKind::Date, $number);
    }

    public function testWithFactsPreservesTheCategory(): void
    {
        $text = Expression::literal('2024-01-02', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        $literal = new TemporalLiteral($text->source, LiteralKind::Date, $text);
        $copy = $literal->withFacts($literal->facts);
        self::assertNotSame($literal, $copy);
        self::assertSame(LiteralKind::Date, $copy->category);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $text = Expression::literal('2024-01-02', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        $literal = new TemporalLiteral($text->source, LiteralKind::Date, $text);
        $this->expectException(InvalidStructure::class);
        $literal->withFacts($text->facts);
    }

    public function testInputsAreEmptyForAConstant(): void
    {
        $text = Expression::literal('2024-01-02', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        self::assertSame([], (new TemporalLiteral($text->source, LiteralKind::Date, $text))->inputs());
    }
}
