<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Retrieval\RetrievedQuery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RetrievedQuery::class)]
#[Medium]
final class RetrievedQueryTest extends TestCase
{
    public function testCheckAcceptsAQueryOfTheFormDialect(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        RetrievedQuery::check(new Origin('s', $query->source, Dialect::MySql), $query, Dialect::MySql);
        $this->addToAssertionCount(1);
    }

    public function testCheckRejectsAnotherDialect(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        RetrievedQuery::check(new Origin('s', $query->source, Dialect::Sqlite), $query, Dialect::MySql);
    }

    public function testWidthCountsResolvedColumnsAndLeavesWildcardsUnknown(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)'));
        $resolved = $binder->bind('SELECT * FROM t');
        $unresolved = $binder->bind('SELECT * FROM unknown', strict: false);
        self::assertInstanceOf(BoundSelect::class, $resolved);
        self::assertInstanceOf(BoundSelect::class, $unresolved);
        self::assertSame(2, RetrievedQuery::width($resolved));
        self::assertNull(RetrievedQuery::width($unresolved));
    }

    public function testCheckRejectsAQueryOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        RetrievedQuery::check($statement->origin, $query, Dialect::MySql);
    }

    public function testWidthLeavesAnEmptyProjectionUnknown(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertNull(RetrievedQuery::width($query));
    }
}
