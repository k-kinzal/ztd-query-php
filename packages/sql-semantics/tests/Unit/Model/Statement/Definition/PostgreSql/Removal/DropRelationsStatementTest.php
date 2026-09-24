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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropRelationsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropRelationsStatement::class)]
#[Medium]
final class DropRelationsStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        self::assertSame(Kind\RelationKind::Sequence, $statement->relationKind);
        self::assertEquals([new QualifiedName(['app', 's1']), new QualifiedName(['s2'])], $statement->names);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame('DROP SEQUENCE IF EXISTS "app"."s1", "s2" CASCADE', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithRelationKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $changed = $statement->withRelationKind(Kind\RelationKind::ForeignTable);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\RelationKind::Sequence, $statement->relationKind);
        self::assertEquals(Kind\RelationKind::ForeignTable, $changed->relationKind);
        self::assertStringContainsString('DROP FOREIGN TABLE', $changed->toString());
    }

    public function testWithNamesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $changed = $statement->withNames([new QualifiedName(['s3'])]);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new QualifiedName(['app', 's1']), new QualifiedName(['s2'])], $statement->names);
        self::assertEquals([new QualifiedName(['s3'])], $changed->names);
        self::assertStringContainsString('EXISTS "s3" CASCADE', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('DROP SEQUENCE "app"', $changed->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE IF EXISTS app.s1, s2 CASCADE', strict: false);
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Restrict);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Cascade, $statement->behavior);
        self::assertEquals(DropBehavior::Restrict, $changed->behavior);
        self::assertStringContainsString('"s2" RESTRICT', $changed->toString());
    }

    public function testRejectsATableRemovedByItsOwnForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SEQUENCE s');
        self::assertInstanceOf(DropRelationsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRelationKind(Kind\RelationKind::Table);
    }
}
