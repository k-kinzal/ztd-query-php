<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SecurityLabelStatement::class)]
#[Medium]
final class SecurityLabelStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'", strict: false);
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['app', 'users'])), $statement->object);
        self::assertSame('selinux', $statement->provider);
        self::assertSame("'x'", $statement->label?->text);
        self::assertSame('SECURITY LABEL FOR "selinux" ON TABLE "app"."users" IS \'x\'', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'", strict: false);
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'", strict: false);
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'", strict: false);
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'id', new QualifiedName(['t'])));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['app', 'users'])), $statement->object);
        self::assertEquals(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'id', new QualifiedName(['t'])), $changed->object);
        self::assertStringContainsString('ON COLUMN "t"."id"', $changed->toString());
    }

    public function testWithProviderReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'", strict: false);
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $changed = $statement->withProvider(null);
        self::assertNotSame($statement, $changed);
        self::assertEquals('selinux', $statement->provider);
        self::assertEquals(null, $changed->provider);
        self::assertStringContainsString('SECURITY LABEL ON TABLE', $changed->toString());
    }

    public function testWithLabelReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'", strict: false);
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $changed = $statement->withLabel(null);
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->label, $statement->label);
        self::assertEquals(null, $changed->label);
        self::assertStringContainsString('IS NULL', $changed->toString());
    }

    public function testRejectsAnIndex(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'");
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\RelationIdentity(Kind\RelationKind::Index, new QualifiedName(['ix'])));
    }

    public function testRejectsAnEmptyProvider(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR selinux ON TABLE app.users IS 'x'");
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withProvider('');
    }

    public function testDecodesAProviderWrittenAsAString(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SECURITY LABEL FOR 'my prov' ON COLUMN t.id IS 'x'");
        self::assertInstanceOf(SecurityLabelStatement::class, $statement);
        self::assertSame('my prov', $statement->provider);
        self::assertSame("SECURITY LABEL FOR \"my prov\" ON COLUMN \"t\".\"id\" IS 'x'", $statement->toString());
    }
}
