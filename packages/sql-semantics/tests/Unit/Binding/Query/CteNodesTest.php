<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\CteNodes;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CteNodes::class)]
#[Medium]
final class CteNodesTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'with_clause'])]
    #[TestWith([Dialect::MySql, 'with_clause'])]
    #[TestWith([Dialect::Sqlite, 'wqlist'])]
    public function testClauseFindsTheStatementLevelWithClauseOfEachDialect(Dialect $dialect, string $name): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertSame($name, CteNodes::clause($statement->origin->source)?->name);
    }

    public function testClauseIgnoresAClauseNestedInsideADerivedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM (WITH c AS (SELECT 1) SELECT * FROM c) q');
        self::assertNull(CteNodes::clause($statement->origin->source));
    }

    public function testClauseDoesNotEnterTheQueryInputOfAnInsert(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $inputOwned = $binder->bind('INSERT INTO t WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertNull(CteNodes::clause($inputOwned->origin->source));
        $statementOwned = $binder->bind('WITH c AS (SELECT 1) INSERT INTO t SELECT * FROM c');
        self::assertSame('with_clause', CteNodes::clause($statementOwned->origin->source)?->name);
    }

    public function testFindWalksQueryWrappersOnlyWhenAsked(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('INSERT INTO t WITH c AS (SELECT 1) SELECT * FROM c');
        self::assertSame('with_clause', CteNodes::find($statement->origin->source, true)?->name);
        self::assertNull(CteNodes::find($statement->origin->source, false));
    }
}
