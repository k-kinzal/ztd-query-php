<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\CursorPosition;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(CursorPosition::class)]
#[Medium]
final class CursorPositionTest extends TestCase
{
    public function testInputsHasNoOperandsForACursorPredicate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('UPDATE t SET id = 1 WHERE CURRENT OF cur');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        $where = $statement->where;
        self::assertInstanceOf(CursorPosition::class, $where);
        self::assertSame(['cur'], $where->cursor);
        self::assertSame([], $where->inputs());
        self::assertSame('boolean', $where->type->name);
        self::assertSame(Nullability::NotNull, $where->nullability);
        self::assertSame('UPDATE "public"."t" SET "id" = 1 WHERE CURRENT OF "cur"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testReferencePartsReturnsTheCursorName(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $position = new CursorPosition($origin->facts, $origin->source, ['app', 'cur']);
        self::assertSame(['app', 'cur'], $position->referenceParts());
        self::assertSame('CURRENT OF "app"."cur"', $position->structure()->toString());
    }

    public function testSpellingIsTheCurrentOfKeyword(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $position = new CursorPosition($origin->facts, $origin->source, ['cur']);
        self::assertSame('CURRENT OF', $position->spelling());
    }

    /**
     * @param list<string> $cursor
     */
    #[TestWith([[]])]
    #[TestWith([['']])]
    public function testRejectsAnEmptyCursorName(array $cursor): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new CursorPosition($origin->facts, $origin->source, $cursor);
    }

    public function testWithFactsKeepsTheCursorName(): void
    {
        $origin = Expression::literal(1, Dialect::PostgreSql);
        $position = new CursorPosition($origin->facts, $origin->source, ['cur']);
        $copy = $position->withFacts(new ExpressionFacts($position->type, Nullability::MaybeNull));
        self::assertNotSame($position, $copy);
        self::assertSame(['cur'], $copy->cursor);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::NotNull, $position->nullability);
    }
}
