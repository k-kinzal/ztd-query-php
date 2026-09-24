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
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(IntroducedLiteral::class)]
#[Medium]
final class IntroducedLiteralTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "_ascii X'2f'", 'ascii', LiteralKind::Binary, 'text'])]
    #[TestWith(['mysql-5.7.44', '_utf8mb4 0x0f', 'utf8mb4', LiteralKind::Binary, 'text'])]
    #[TestWith(['mysql-8.0.44', "_binary b'01'", 'binary', LiteralKind::BitString, 'blob'])]
    #[TestWith(['mysql-8.4.7', "_LATIN1 'a'", 'latin1', LiteralKind::Text, 'text'])]
    #[TestWith(['mysql-9.1.0', '_binary 0b101', 'binary', LiteralKind::BitString, 'blob'])]
    public function testSpellingKeepsTheIntroducerAndTheLiteral(string $version, string $sql, string $characterSet, LiteralKind $kind, string $type): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $query = $binder->bind('SELECT ' . $sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $literal = $query->outputs[0]->expression;
        self::assertInstanceOf(IntroducedLiteral::class, $literal);
        self::assertSame($characterSet, $literal->characterSet);
        self::assertSame($kind, $literal->literal->literalKind);
        self::assertSame($type, $literal->type->name);
        self::assertSame(Nullability::NotNull, $literal->nullability);
        self::assertSame(ExpressionKind::Literal, $literal->kind);
        self::assertSame([], $literal->inputs());
        self::assertSame('SELECT ' . $literal->spelling(), $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testSpellingRejectsANumberLiteral(): void
    {
        $number = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        new IntroducedLiteral($number->source, 'utf8mb4', $number);
    }

    public function testSpellingRejectsAMalformedCharacterSetName(): void
    {
        $text = Expression::literal('a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        $this->expectException(InvalidStructure::class);
        new IntroducedLiteral($text->source, 'UTF8 MB4', $text);
    }

    public function testSpellingRejectsAnotherDialect(): void
    {
        $text = Expression::literal('a', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $text);
        $this->expectException(InvalidStructure::class);
        new IntroducedLiteral($text->source, 'utf8', $text);
    }

    public function testWithFactsPreservesTheIntroducer(): void
    {
        $text = Expression::literal('a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        $literal = new IntroducedLiteral($text->source, 'utf8mb4', $text);
        $copy = $literal->withFacts($literal->facts);
        self::assertNotSame($literal, $copy);
        self::assertSame('utf8mb4', $copy->characterSet);
        self::assertSame($text, $copy->literal);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $text = Expression::literal('a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        $literal = new IntroducedLiteral($text->source, 'binary', $text);
        $this->expectException(InvalidStructure::class);
        $literal->withFacts($text->facts);
    }

    public function testInputsAreEmptyForAConstant(): void
    {
        $text = Expression::literal('a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $text);
        self::assertSame([], (new IntroducedLiteral($text->source, 'utf8mb4', $text))->inputs());
    }
}
