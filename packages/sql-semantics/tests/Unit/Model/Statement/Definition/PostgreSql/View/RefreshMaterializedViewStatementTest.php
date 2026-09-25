<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\RefreshMaterializedViewStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RefreshMaterializedViewStatement::class)]
#[Medium]
final class RefreshMaterializedViewStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRefreshOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REFRESH MATERIALIZED VIEW CONCURRENTLY s.m');
        self::assertInstanceOf(RefreshMaterializedViewStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame(StatementKind::Refresh, $copy->kind);
        self::assertTrue($copy->concurrently);
        self::assertTrue($copy->withData);
        self::assertSame('REFRESH MATERIALIZED VIEW CONCURRENTLY "s"."m"', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REFRESH MATERIALIZED VIEW m');
        self::assertInstanceOf(RefreshMaterializedViewStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameChangesTheTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REFRESH MATERIALIZED VIEW m WITH NO DATA');
        self::assertInstanceOf(RefreshMaterializedViewStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['s', 'n']));
        self::assertSame(['m'], $statement->name->parts);
        self::assertSame(['s', 'n'], $changed->name->parts);
        self::assertSame('REFRESH MATERIALIZED VIEW "s"."n" WITH NO DATA', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAConcurrentRefreshWithoutData(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REFRESH MATERIALIZED VIEW m');
        self::assertInstanceOf(RefreshMaterializedViewStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new RefreshMaterializedViewStatement($statement->origin, $statement->name, concurrently: true, withData: false);
    }

}
