<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\SchemaCreation;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Schema as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaCreation::class)]
#[Medium]
final class SchemaCreationTest extends TestCase
{
    public function testBindSeparatesNamedAndOwnerSchemas(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $named = $binder->bind('CREATE SCHEMA IF NOT EXISTS app AUTHORIZATION SESSION_USER');
        self::assertInstanceOf(Statement\CreateSchemaStatement::class, $named);
        self::assertTrue($named->ifNotExists);
        self::assertSame(SessionRole::SessionUser, $named->owner);
        self::assertInstanceOf(Statement\CreateAuthorizationSchemaStatement::class, $binder->bind('CREATE SCHEMA AUTHORIZATION alice'));
    }

    #[TestWith(['CREATE SCHEMA IF NOT EXISTS app AUTHORIZATION alice CREATE TABLE t (id integer)'])]
    #[TestWith(['CREATE SCHEMA app CREATE TABLE other.t (id integer)'])]
    #[TestWith(['CREATE SCHEMA app CREATE TEMP TABLE t (id integer)'])]
    public function testBindDiagnosesElementsOutsideTheNewSchema(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SchemaElement->message());
        $binder->bind($sql);
    }

    public function testElementsResolveUnqualifiedNamesInTheNewSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app CREATE TABLE t (id integer) CREATE VIEW v AS SELECT 1 CREATE INDEX i ON t (id) GRANT SELECT ON t TO bob', strict: false);
        self::assertInstanceOf(Statement\CreateSchemaStatement::class, $statement);
        self::assertCount(4, $statement->elements);
        self::assertInstanceOf(CreateTableStatement::class, $statement->elements[0]);
        self::assertSame('', $statement->elements[0]->definition->table->schema);
    }

    public function testPlacementAcceptsTheSchemaItselfAsQualifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA AUTHORIZATION alice CREATE TABLE alice.t (id integer)');
        self::assertInstanceOf(Statement\CreateAuthorizationSchemaStatement::class, $statement);
        self::assertSame('CREATE SCHEMA AUTHORIZATION "alice" CREATE TABLE "alice"."t"("id" integer)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
