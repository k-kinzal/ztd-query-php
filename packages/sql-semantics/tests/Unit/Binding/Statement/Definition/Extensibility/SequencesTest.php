<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Extensibility\Sequences;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Statement\Definition\Sequence as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Sequences::class)]
#[Medium]
final class SequencesTest extends TestCase
{
    #[TestWith(['CREATE SEQUENCE s', Statement\SequencePersistence::Permanent, 'CREATE SEQUENCE "s"'])]
    #[TestWith(['CREATE GLOBAL TEMPORARY SEQUENCE s', Statement\SequencePersistence::Temporary, 'CREATE TEMPORARY SEQUENCE "s"'])]
    #[TestWith(['CREATE UNLOGGED SEQUENCE s CACHE 1_000', Statement\SequencePersistence::Unlogged, 'CREATE UNLOGGED SEQUENCE "s" CACHE 1000'])]
    public function testCreateReadsThePersistence(string $sql, Statement\SequencePersistence $persistence, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(Statement\CreateSequenceStatement::class, $statement);
        self::assertSame($persistence, $statement->persistence);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['CREATE SEQUENCE s MINVALUE -3 START WITH -5'])]
    #[TestWith(['CREATE SEQUENCE s AS smallint MAXVALUE 40000'])]
    #[TestWith(['CREATE SEQUENCE s INCREMENT BY -1 MINVALUE 5'])]
    #[TestWith(['CREATE SEQUENCE s SEQUENCE NAME x'])]
    #[TestWith(['CREATE SEQUENCE s LOGGED'])]
    #[TestWith(['CREATE SEQUENCE s CACHE 0'])]
    #[TestWith(['CREATE SEQUENCE s START 1.5'])]
    #[TestWith(['CREATE SEQUENCE s OWNED BY a'])]
    #[TestWith(['CREATE TEMP SEQUENCE app.s'])]
    public function testCreateRejectsImpossibleOptions(string $sql): void
    {
        try {
            (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
            self::fail('The options must be diagnosed.');
        } catch (InvalidSql $error) {
            self::assertSame(InputViolation::SequenceDefinition, $error->violation);
        }
    }

    public function testCreateBindsAsASchemaElement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app CREATE SEQUENCE q START 5');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Schema\CreateSchemaStatement::class, $statement);
        self::assertInstanceOf(Statement\CreateSequenceStatement::class, $statement->elements[0]);
        self::assertSame('CREATE SCHEMA "app" CREATE SEQUENCE "q" START WITH 5', $statement->toString());
    }

    public function testAlterChecksOnlyTheExplicitBounds(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s START WITH -5 RESTART WITH 0');
        self::assertInstanceOf(Statement\AlterSequenceStatement::class, $statement);
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s MINVALUE 10 MAXVALUE 10');
    }

    public function testNameReadsTheQualifiedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE db.app.s CYCLE');
        self::assertInstanceOf(Statement\AlterSequenceStatement::class, $statement);
        self::assertSame(['db', 'app', 's'], $statement->name->parts);
    }

    #[TestWith(['CREATE SEQUENCE IF NOT EXISTS s', true])]
    #[TestWith(['CREATE SEQUENCE if', false])]
    public function testConditionalReadsTheExistencePolicy(string $sql, bool $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\CreateSequenceStatement::class, $statement);
        self::assertSame($expected, $statement->ifNotExists);
    }

    public function testOptionsKeepsTheRequestOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s NO CYCLE INCREMENT 3 NO MINVALUE');
        self::assertInstanceOf(Statement\AlterSequenceStatement::class, $statement);
        self::assertSame(Identity\SequenceFlag::NoCycle, $statement->options[0]);
        self::assertSame(Identity\SequenceFlag::NoMinValue, $statement->options[2]);
    }

    public function testOptionRejectsARepeatedOption(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s CYCLE CYCLE');
    }

    public function testOwnerReadsNone(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s OWNED BY NONE');
        self::assertInstanceOf(Statement\AlterSequenceStatement::class, $statement);
        self::assertEquals(new Identity\SetSequenceOwner(null), $statement->options[0]);
    }

    #[TestWith(['-9223372036854775808', '-9223372036854775808'])]
    #[TestWith(['+1_2', '12'])]
    public function testNumberFoldsSignAndSeparators(string $written, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SEQUENCE s MINVALUE ' . $written);
        self::assertInstanceOf(Statement\AlterSequenceStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(Identity\SequenceValueChange::class, $option);
        self::assertSame($expected, $option->value->text);
    }

    #[TestWith(['create unlogged sequence s', 'CREATE UNLOGGED SEQUENCE "s"'])]
    #[TestWith(['create sequence if not exists s', 'CREATE SEQUENCE IF NOT EXISTS "s"'])]
    #[TestWith(['ALTER SEQUENCE a.b.s CYCLE', 'ALTER SEQUENCE "a"."b"."s" CYCLE'])]
    #[TestWith(['ALTER SEQUENCE s OWNED BY a.b.t.id', 'ALTER SEQUENCE "s" OWNED BY "a"."b"."t"."id"'])]
    #[TestWith(['ALTER SEQUENCE s RESTART', 'ALTER SEQUENCE "s" RESTART'])]
    #[TestWith(['ALTER SEQUENCE s RESTART 5', 'ALTER SEQUENCE "s" RESTART WITH 5'])]
    public function testBindSpellsTheAcceptedForms(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    #[TestWith(['ALTER SEQUENCE a.b.c.s CYCLE'])]
    #[TestWith(['ALTER SEQUENCE s OWNED BY x.a.b.t.id'])]
    public function testNameRejectsAnOverQualifiedName(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    #[TestWith(['ALTER SEQUENCE s MINVALUE 9223372036854775808'])]
    #[TestWith(['ALTER SEQUENCE s MINVALUE 0x10'])]
    #[TestWith(['ALTER SEQUENCE s MINVALUE 1e3'])]
    #[TestWith(['ALTER SEQUENCE s UNLOGGED'])]
    public function testOptionRejectsAnImpossibleValue(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SequenceDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }
}
