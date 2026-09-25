<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\RelationIdentity::class)]
#[Medium]
final class RelationIdentityTest extends TestCase
{
    public function testRetainsTheRelationClassAndName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON MATERIALIZED VIEW app.totals IS 'daily'");
        self::assertInstanceOf(Statement\CommentOnStatement::class, $statement);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::MaterializedView, new QualifiedName(['app', 'totals'])), $statement->object);
        self::assertSame("COMMENT ON MATERIALIZED VIEW \"app\".\"totals\" IS 'daily'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['a', 'b', 'c', 'd']));
    }
}
