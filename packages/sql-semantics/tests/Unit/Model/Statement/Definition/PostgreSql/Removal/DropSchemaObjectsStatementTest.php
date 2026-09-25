<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Removal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropSchemaObjectsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropSchemaObjectsStatement::class)]
#[Medium]
final class DropSchemaObjectsStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        self::assertSame(Kind\SchemaObjectKind::TextSearchConfiguration, $statement->objectKind);
        self::assertEquals([new QualifiedName(['app', 'english'])], $statement->names);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
        self::assertSame('DROP TEXT SEARCH CONFIGURATION IF EXISTS "app"."english" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $changed = $statement->withObjectKind(Kind\SchemaObjectKind::Collation);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\SchemaObjectKind::TextSearchConfiguration, $statement->objectKind);
        self::assertEquals(Kind\SchemaObjectKind::Collation, $changed->objectKind);
        self::assertStringContainsString('DROP COLLATION', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNamesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $changed = $statement->withNames([new QualifiedName(['c'])]);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new QualifiedName(['app', 'english'])], $statement->names);
        self::assertEquals([new QualifiedName(['c'])], $changed->names);
        self::assertStringContainsString('EXISTS "c" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('CONFIGURATION "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TEXT SEARCH CONFIGURATION IF EXISTS app.english RESTRICT', strict: false);
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Cascade);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Restrict, $statement->behavior);
        self::assertEquals(DropBehavior::Cascade, $changed->behavior);
        self::assertStringContainsString('"english" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnOverQualifiedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP COLLATION c');
        self::assertInstanceOf(DropSchemaObjectsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNames([new QualifiedName(['a', 'b', 'c'])]);
    }
}
