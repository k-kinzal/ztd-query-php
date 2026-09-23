<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\View\DropMaterializedViewsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropMaterializedViewsStatement::class)]
#[Medium]
final class DropMaterializedViewsStatementTest extends TestCase
{
    public function testWithOriginRetainsSelectionAndDependencyPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP MATERIALIZED VIEW IF EXISTS a, s.b CASCADE');
        self::assertInstanceOf(DropMaterializedViewsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertTrue($copy->ifExists);
        self::assertSame(DropBehavior::Cascade, $copy->behavior);
        self::assertSame('DROP MATERIALIZED VIEW IF EXISTS "a", "s"."b" CASCADE', $copy->toString());
    }

    public function testWithOriginRejectsAnotherLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP MATERIALIZED VIEW m');
        self::assertInstanceOf(DropMaterializedViewsStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNamesReplacesTheCompleteSelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP MATERIALIZED VIEW m');
        self::assertInstanceOf(DropMaterializedViewsStatement::class, $statement);
        $changed = $statement->withNames([new QualifiedName(['x"y']), new QualifiedName(['s', 'z'])]);
        self::assertCount(1, $statement->names);
        self::assertCount(2, $changed->names);
        self::assertSame('DROP MATERIALIZED VIEW "x""y", "s"."z"', $changed->toString());
    }

    public function testWithNamesRejectsAnEmptySelection(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP MATERIALIZED VIEW m');
        self::assertInstanceOf(DropMaterializedViewsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNames([]);
    }

}
