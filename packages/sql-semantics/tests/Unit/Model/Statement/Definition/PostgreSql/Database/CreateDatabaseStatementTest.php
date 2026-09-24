<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\CreateDatabaseStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateDatabaseStatement::class)]
#[Medium]
final class CreateDatabaseStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRequestedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DATABASE app OWNER alice');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertEquals($statement->options, $copy->options);
        self::assertSame(StatementKind::Create, $copy->kind);
    }

    public function testWithOriginRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DATABASE app');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNameReplacesOnlyTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DATABASE app OWNER alice');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $changed = $statement->withName('a"b');
        self::assertSame('app', $statement->name);
        self::assertSame('a"b', $changed->name);
        self::assertSame('CREATE DATABASE "a""b" WITH OWNER = \'alice\'', $changed->toString());
    }

    public function testWithOptionsReplacesTheProperties(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DATABASE app OWNER alice');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        $changed = $statement->withOptions([new DatabaseOption(DatabaseParameter::Oid, 20000), new DatabaseOption(DatabaseParameter::Template, null)]);
        self::assertCount(1, $statement->options);
        self::assertSame('CREATE DATABASE "app" WITH OID = 20000 TEMPLATE = DEFAULT', $changed->toString());
    }
}
