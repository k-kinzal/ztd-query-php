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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropRelationMemberStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropRelationMemberStatement::class)]
#[Medium]
final class DropRelationMemberStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        self::assertSame(Kind\RelationMemberKind::Policy, $statement->memberKind);
        self::assertSame('owner_only', $statement->name);
        self::assertSame(['app', 'docs'], $statement->table->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame('DROP POLICY IF EXISTS "owner_only" ON "app"."docs" CASCADE', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithMemberKindReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $changed = $statement->withMemberKind(Kind\RelationMemberKind::Rule);
        self::assertNotSame($statement, $changed);
        self::assertEquals(Kind\RelationMemberKind::Policy, $statement->memberKind);
        self::assertEquals(Kind\RelationMemberKind::Rule, $changed->memberKind);
        self::assertStringContainsString('DROP RULE', $changed->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $changed = $statement->withName('p');
        self::assertNotSame($statement, $changed);
        self::assertEquals('owner_only', $statement->name);
        self::assertEquals('p', $changed->name);
        self::assertStringContainsString('EXISTS "p" ON', $changed->toString());
    }

    public function testWithTableReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $changed = $statement->withTable(new QualifiedName(['docs']));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new QualifiedName(['app', 'docs']), $statement->table);
        self::assertEquals(new QualifiedName(['docs']), $changed->table);
        self::assertStringContainsString('ON "docs"', $changed->toString());
    }

    public function testWithIfExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $changed = $statement->withIfExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifExists);
        self::assertEquals(false, $changed->ifExists);
        self::assertStringContainsString('DROP POLICY "owner_only"', $changed->toString());
    }

    public function testWithBehaviorReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP POLICY IF EXISTS owner_only ON app.docs CASCADE', strict: false);
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Default);
        self::assertNotSame($statement, $changed);
        self::assertEquals(DropBehavior::Cascade, $statement->behavior);
        self::assertEquals(DropBehavior::Default, $changed->behavior);
        self::assertStringContainsString('ON "app"."docs"', $changed->toString());
    }

    public function testRejectsATriggerRemovedByItsOwnForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP RULE r ON t');
        self::assertInstanceOf(DropRelationMemberStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withMemberKind(Kind\RelationMemberKind::Trigger);
    }
}
