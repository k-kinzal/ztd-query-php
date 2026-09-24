<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\ChangeObjectOwnerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeObjectOwnerStatement::class)]
#[Medium]
final class ChangeObjectOwnerStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER', strict: false);
        self::assertInstanceOf(ChangeObjectOwnerStatement::class, $statement);
        self::assertEquals(new Catalog\NamedIdentity(Kind\NamedObjectKind::Schema, 'app'), $statement->object);
        self::assertSame(SessionRole::SessionUser, $statement->newOwner);
        self::assertSame('ALTER SCHEMA "app" OWNER TO SESSION_USER', $statement->toString());
        self::assertSame($statement->toString(), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($statement->toString(), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER', strict: false);
        self::assertInstanceOf(ChangeObjectOwnerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER', strict: false);
        self::assertInstanceOf(ChangeObjectOwnerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER', strict: false);
        self::assertInstanceOf(ChangeObjectOwnerStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['c'])));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new Catalog\NamedIdentity(Kind\NamedObjectKind::Schema, 'app'), $statement->object);
        self::assertEquals(new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['c'])), $changed->object);
        self::assertStringContainsString('ALTER COLLATION "c" OWNER TO', $changed->toString());
    }

    public function testWithNewOwnerReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER', strict: false);
        self::assertInstanceOf(ChangeObjectOwnerStatement::class, $statement);
        $changed = $statement->withNewOwner(new NamedRole('bob'));
        self::assertNotSame($statement, $changed);
        self::assertEquals(SessionRole::SessionUser, $statement->newOwner);
        self::assertEquals(new NamedRole('bob'), $changed->newOwner);
        self::assertStringContainsString('OWNER TO "bob"', $changed->toString());
    }

    public function testRejectsARelationWhoseOwnerChangesInsideAlterTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SCHEMA app OWNER TO SESSION_USER');
        self::assertInstanceOf(ChangeObjectOwnerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t'])));
    }
}
