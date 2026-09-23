<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\Model\Query\WithClause;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WithClause::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class WithClauseTest extends TestCase
{
    public function testPreservesDeclarationOrderAndRecursivePolicy(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $first = new CommonTableExpression('first', $query);
        $second = new CommonTableExpression('second', $query);
        $with = new WithClause([$first, $second], true);
        self::assertSame([$first, $second], $with->definitions);
        self::assertTrue($with->recursive);
    }

    public function testRejectsEmptyDefinitions(): void
    {
        $this->expectException(InvalidStructure::class);
        new WithClause([]);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRejectsCaseInsensitiveDuplicateAliases(Dialect $dialect): void
    {
        $query = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new WithClause([new CommonTableExpression('q', $query), new CommonTableExpression('Q', $query)]);
    }

    public function testAllowsDistinctPostgresqlQuotedAliases(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $with = new WithClause([new CommonTableExpression('q', $query), new CommonTableExpression('Q', $query)]);
        self::assertCount(2, $with->definitions);
    }

    public function testRejectsMixedDialectDefinitions(): void
    {
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $sqlite = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $postgres);
        self::assertInstanceOf(BoundSelect::class, $sqlite);
        $this->expectException(InvalidStructure::class);
        new WithClause([new CommonTableExpression('p', $postgres), new CommonTableExpression('s', $sqlite)]);
    }
}
