<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Text\Trim;
use SqlSemantics\Model\Scalar\Text\TrimSide;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Trim::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TrimTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::PostgreSql])]
    public function testInputsListsTheRemovedCharactersBeforeTheString(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind("SELECT TRIM(TRAILING 'x' FROM 'axx')");
        self::assertInstanceOf(BoundSelect::class, $query);
        $trim = $query->outputs[0]->expression;
        self::assertInstanceOf(Trim::class, $trim);
        self::assertSame(TrimSide::Trailing, $trim->side);
        self::assertNotNull($trim->characters);
        self::assertSame("'x'", $trim->characters->spelling());
        self::assertSame("'axx'", $trim->string->spelling());
        self::assertSame([$trim->characters, $trim->string], $trim->inputs());
        self::assertSame('text', $trim->type->name);
        self::assertSame(Nullability::NotNull, $trim->nullability);
        self::assertSame("SELECT TRIM(TRAILING 'x' FROM 'axx')", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsListsOnlyTheStringWithoutRemovedCharacters(): void
    {
        $string = Expression::literal('a', Dialect::MySql);
        $trim = new Trim($string->source, TrimSide::Both, null, $string);
        self::assertSame([$string], $trim->inputs());
        self::assertNull($trim->characters);
    }

    public function testInputsRejectsMixedDialects(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new Trim($string->source, TrimSide::Both, Expression::literal('x', Dialect::MySql), $string);
    }

    public function testInputsRejectsSqlite(): void
    {
        $value = Expression::literal('a', Dialect::Sqlite);
        $this->expectException(InvalidStructure::class);
        new Trim($value->source, TrimSide::Both, null, $value);
    }

    public function testSpellingIdentifiesATrim(): void
    {
        $value = Expression::literal(null, Dialect::PostgreSql);
        $trim = new Trim($value->source, TrimSide::Leading, $value, Expression::literal('a', Dialect::PostgreSql));
        self::assertSame('TRIM', $trim->spelling());
        self::assertSame(Nullability::AlwaysNull, $trim->nullability);
    }

    public function testWithFactsPreservesTheTrimOperands(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $trim = new Trim($string->source, TrimSide::Leading, null, $string);
        $copy = $trim->withFacts($trim->facts);
        self::assertNotSame($trim, $copy);
        self::assertSame(TrimSide::Leading, $copy->side);
        self::assertSame($string, $copy->string);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $string = Expression::literal('a', Dialect::PostgreSql);
        $trim = new Trim($string->source, TrimSide::Both, null, $string);
        $this->expectException(InvalidStructure::class);
        $trim->withFacts($string->facts);
    }

    public function testInputsAreReboundAfterAnImmutableChange(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT TRIM('x' FROM 'axx')");
        self::assertInstanceOf(BoundSelect::class, $query);
        $trim = $query->outputs[0]->expression;
        self::assertInstanceOf(Trim::class, $trim);
        $copy = $query->replaceExpression($trim->string, Expression::literal(null, Dialect::MySql));
        self::assertSame("SELECT TRIM(BOTH 'x' FROM NULL)", $copy->toString());
        self::assertSame(Nullability::AlwaysNull, $copy->outputs[0]->expression->nullability);
        self::assertSame(Nullability::NotNull, $trim->nullability);
    }
}
