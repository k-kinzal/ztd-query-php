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
use SqlSemantics\Model\Statement\Definition\Sequence\CreateSequenceStatement;
use SqlSemantics\Model\Statement\Definition\Sequence\SequencePersistence;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateSequenceStatement::class)]
#[Medium]
final class CreateSequenceStatementTest extends TestCase
{
    public function testBindsNegativeValuesWithTheirSign(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE SEQUENCE IF NOT EXISTS app.s INCREMENT BY -2 MINVALUE -3 START WITH -3 NO MAXVALUE');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        self::assertSame([['app', 's'], SequencePersistence::Permanent, true], [$statement->name->parts, $statement->persistence, $statement->ifNotExists]);
        $increment = $statement->options[0];
        self::assertInstanceOf(Identity\SequenceValueChange::class, $increment);
        self::assertSame('-2', $increment->value->text);
        self::assertSame('CREATE SEQUENCE IF NOT EXISTS "app"."s" INCREMENT BY -2 MINVALUE -3 START WITH -3 NO MAXVALUE', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsATemporarySequenceInAPermanentSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEMP SEQUENCE pg_temp.s');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName(new QualifiedName(['app', 's']));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE UNLOGGED SEQUENCE s CYCLE');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE UNLOGGED SEQUENCE "s" CYCLE', $copy->toString());
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SEQUENCE s');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin);
    }

    public function testWithNameReplacesTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SEQUENCE s');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        self::assertSame('CREATE SEQUENCE "app"."s2"', $statement->withName(new QualifiedName(['app', 's2']))->toString());
        self::assertSame(['s'], $statement->name->parts);
    }

    public function testWithPersistenceReplacesTheLifetime(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SEQUENCE s');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        self::assertSame('CREATE TEMPORARY SEQUENCE "s"', $statement->withPersistence(SequencePersistence::Temporary)->toString());
        self::assertSame(SequencePersistence::Permanent, $statement->persistence);
    }

    public function testWithIfNotExistsReplacesTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SEQUENCE s');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        self::assertSame('CREATE SEQUENCE IF NOT EXISTS "s"', $statement->withIfNotExists(true)->toString());
        self::assertFalse($statement->ifNotExists);
    }

    public function testWithOptionsReplacesTheOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SEQUENCE s CYCLE');
        self::assertInstanceOf(CreateSequenceStatement::class, $statement);
        self::assertSame('CREATE SEQUENCE "s" NO CYCLE OWNED BY NONE', $statement->withOptions([Identity\SequenceFlag::NoCycle, new Identity\SetSequenceOwner(null)])->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([Identity\SequenceFlag::Unlogged]);
    }
}
