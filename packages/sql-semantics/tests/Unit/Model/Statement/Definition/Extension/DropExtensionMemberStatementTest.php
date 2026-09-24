<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Extension\DropExtensionMemberStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropExtensionMemberStatement::class)]
#[Medium]
final class DropExtensionMemberStatementTest extends TestCase
{
    public function testBindsTheMemberAndWritesItBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER EXTENSION hstore DROP OPERATOR CLASS app.ops USING btree');
        self::assertInstanceOf(DropExtensionMemberStatement::class, $statement);
        self::assertSame('hstore', $statement->extension);
        self::assertEquals(new Catalog\OperatorSetIdentity(Kind\OperatorSetKind::OperatorClass, new QualifiedName(['app', 'ops']), 'btree'), $statement->object);
        self::assertSame('ALTER EXTENSION "hstore" DROP OPERATOR CLASS "app"."ops" USING "btree"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore DROP SCHEMA app');
        self::assertInstanceOf(DropExtensionMemberStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER EXTENSION "hstore" DROP SCHEMA "app"', $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore DROP SCHEMA app');
        self::assertInstanceOf(DropExtensionMemberStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithExtensionReplacesTheExtension(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore DROP SCHEMA app');
        self::assertInstanceOf(DropExtensionMemberStatement::class, $statement);
        $changed = $statement->withExtension('citext');
        self::assertSame('hstore', $statement->extension);
        self::assertSame('ALTER EXTENSION "citext" DROP SCHEMA "app"', $changed->toString());
    }

    public function testWithObjectReplacesTheMember(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EXTENSION hstore DROP SCHEMA app');
        self::assertInstanceOf(DropExtensionMemberStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\RoutineIdentity(Kind\RoutineKind::Procedure, new \SqlSemantics\Model\Definition\Routine\RoutineByName(new QualifiedName(['p']))));
        self::assertSame('ALTER EXTENSION "hstore" DROP SCHEMA "app"', $statement->toString());
        self::assertSame('ALTER EXTENSION "hstore" DROP PROCEDURE "p"', $changed->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\DomainConstraintIdentity('c', new QualifiedName(['d'])));
    }
}
