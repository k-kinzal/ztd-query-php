<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\Sequence\AlterSequenceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterSequenceStatement::class)]
#[Medium]
final class AlterSequenceStatementTest extends TestCase
{
    public function testBindsTheChangesAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id BIGINT)'));
        $statement = $binder->bind('ALTER SEQUENCE IF EXISTS s AS bigint RESTART START 5 OWNED BY t.id');
        self::assertInstanceOf(AlterSequenceStatement::class, $statement);
        self::assertSame([['s'], true, 4], [$statement->name->parts, $statement->ifExists, count($statement->options)]);
        self::assertEquals(new Identity\RestartIdentity(null), $statement->options[1]);
        self::assertSame('ALTER SEQUENCE IF EXISTS "s" AS bigint RESTART START WITH 5 OWNED BY "t"."id"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE');
        self::assertInstanceOf(AlterSequenceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('ALTER SEQUENCE "s" CYCLE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE');
        self::assertInstanceOf(AlterSequenceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheSequence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE');
        self::assertInstanceOf(AlterSequenceStatement::class, $statement);
        self::assertSame('ALTER SEQUENCE "app"."s" CYCLE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['app', 's']))));
        self::assertSame(['s'], $statement->name->parts);
    }

    public function testWithIfExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE');
        self::assertInstanceOf(AlterSequenceStatement::class, $statement);
        self::assertSame('ALTER SEQUENCE IF EXISTS "s" CYCLE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withIfExists(true)));
        self::assertFalse($statement->ifExists);
    }

    public function testWithOptionsRejectsAPersistenceFlag(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE');
        self::assertInstanceOf(AlterSequenceStatement::class, $statement);
        self::assertSame('ALTER SEQUENCE "s" NO MINVALUE', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOptions([Identity\SequenceFlag::NoMinValue])));
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([Identity\SequenceFlag::Logged]);
    }
}
