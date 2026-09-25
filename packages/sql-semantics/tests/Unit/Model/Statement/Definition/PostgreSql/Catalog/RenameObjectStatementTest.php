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
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameObjectStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameObjectStatement::class)]
#[Medium]
final class RenameObjectStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old', strict: false);
        self::assertInstanceOf(RenameObjectStatement::class, $statement);
        self::assertEquals(new Catalog\NamedIdentity(Kind\NamedObjectKind::Language, 'plperl'), $statement->object);
        self::assertSame('plperl_old', $statement->newName);
        self::assertSame('ALTER LANGUAGE "plperl" RENAME TO "plperl_old"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old', strict: false);
        self::assertInstanceOf(RenameObjectStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old', strict: false);
        self::assertInstanceOf(RenameObjectStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithObjectReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old', strict: false);
        self::assertInstanceOf(RenameObjectStatement::class, $statement);
        $changed = $statement->withObject(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'a', new QualifiedName(['t'])));
        self::assertNotSame($statement, $changed);
        self::assertEquals(new Catalog\NamedIdentity(Kind\NamedObjectKind::Language, 'plperl'), $statement->object);
        self::assertEquals(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'a', new QualifiedName(['t'])), $changed->object);
        self::assertStringContainsString('ALTER TRIGGER "a" ON "t" RENAME TO', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithNewNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old', strict: false);
        self::assertInstanceOf(RenameObjectStatement::class, $statement);
        $changed = $statement->withNewName('pl');
        self::assertNotSame($statement, $changed);
        self::assertEquals('plperl_old', $statement->newName);
        self::assertEquals('pl', $changed->newName);
        self::assertStringContainsString('RENAME TO "pl"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsARelationWhichRenamesWithItsOwnForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER PROCEDURAL LANGUAGE plperl RENAME TO plperl_old');
        self::assertInstanceOf(RenameObjectStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withObject(new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t'])));
    }
}
