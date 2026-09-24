<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Query\ExistsSubquery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ExistsSubquery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExistsSubqueryTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testInputsAreTheSubqueryResultColumns(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (n INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT EXISTS (SELECT n FROM t WHERE n > 0)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $exists = $statement->outputs[0]->expression;
        self::assertInstanceOf(ExistsSubquery::class, $exists);
        self::assertCount(1, $exists->inputs());
        self::assertSame('n', $exists->inputs()[0]->columnBinding()?->column->name);
        self::assertSame(['n'], array_map(static fn ($binding): string => $binding->column->name, $exists->lineage()));
        self::assertSame(ExpressionKind::Subquery, $exists->kind);
    }

    public function testSpellingIsExists(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXISTS (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $exists = $statement->outputs[0]->expression;
        self::assertInstanceOf(ExistsSubquery::class, $exists);
        self::assertSame('EXISTS', $exists->spelling());
        self::assertSame('boolean', $exists->type->name);
    }

    public function testSubqueryReturnsTheBoundQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXISTS (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $exists = $statement->outputs[0]->expression;
        self::assertInstanceOf(ExistsSubquery::class, $exists);
        self::assertSame($exists->query, $exists->subquery());
        self::assertSame('1', $exists->query->resultColumns()[0]->expression->spelling());
    }

    public function testWithFactsKeepsTheQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXISTS (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $exists = $statement->outputs[0]->expression;
        self::assertInstanceOf(ExistsSubquery::class, $exists);
        $changed = $exists->withFacts(new ExpressionFacts($exists->type, Nullability::MaybeNull, ['j0']));
        self::assertNotSame($exists, $changed);
        self::assertSame(['j0'], $changed->nullExtendedBy);
        self::assertSame([], $exists->nullExtendedBy);
        self::assertSame($exists->query, $changed->query);
    }

    public function testRejectsAQueryFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT EXISTS (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $exists = $statement->outputs[0]->expression;
        self::assertInstanceOf(ExistsSubquery::class, $exists);
        $foreign = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $foreign);
        $this->expectException(InvalidStructure::class);
        new ExistsSubquery($exists->facts, $exists->source, $foreign);
    }

    public function testSerializesThePredicateWithItsQuery(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (n INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('SELECT EXISTS (SELECT n FROM t WHERE n > 0)');
        self::assertSame('SELECT EXISTS(SELECT "n" AS "n" FROM "public"."t" WHERE ("n" > 0))', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
