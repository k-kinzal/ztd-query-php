<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Schema\CreateSchemaStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateSchemaStatement::class)]
#[Medium]
final class CreateSchemaStatementTest extends TestCase
{
    public function testWithOriginRetainsTheElements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app CREATE TABLE t (id integer)');
        self::assertInstanceOf(CreateSchemaStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertCount(1, $copy->elements);
        self::assertSame(StatementKind::Create, $copy->kind);
    }

    public function testWithNameRebindsTheElementsInsideTheRenamedSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app CREATE TABLE t (id integer)');
        self::assertInstanceOf(CreateSchemaStatement::class, $statement);
        $changed = $statement->withName('other');
        self::assertInstanceOf(CreateTableStatement::class, $changed->elements[0]);
        self::assertSame('CREATE SCHEMA "other" CREATE TABLE "t"("id" integer)', $changed->toString());
    }

    public function testWithOwnerReplacesTheOwningRole(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app');
        self::assertInstanceOf(CreateSchemaStatement::class, $statement);
        $changed = $statement->withOwner(new NamedRole('alice'));
        self::assertSame('CREATE SCHEMA "app" AUTHORIZATION "alice"', $changed->toString());
        self::assertNull($statement->owner);
    }

    public function testWithIfNotExistsRejectsExistingElements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app');
        self::assertInstanceOf(CreateSchemaStatement::class, $statement);
        self::assertTrue($statement->withIfNotExists(true)->ifNotExists);
        $populated = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app CREATE TABLE t (id integer)');
        self::assertInstanceOf(CreateSchemaStatement::class, $populated);
        $this->expectException(InvalidStructure::class);
        $populated->withIfNotExists(true);
    }

    public function testWithElementsReplacesTheNestedCommands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SCHEMA app CREATE TABLE t (id integer)');
        self::assertInstanceOf(CreateSchemaStatement::class, $statement);
        self::assertSame('CREATE SCHEMA "app"', $statement->withElements([])->toString());
    }
}
