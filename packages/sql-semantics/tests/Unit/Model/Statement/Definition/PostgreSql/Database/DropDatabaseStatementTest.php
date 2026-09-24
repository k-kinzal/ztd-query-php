<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\DropDatabaseStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropDatabaseStatement::class)]
#[Medium]
final class DropDatabaseStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRemovalPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DATABASE IF EXISTS app WITH (FORCE, FORCE)');
        self::assertInstanceOf(DropDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertTrue($copy->ifExists);
        self::assertTrue($copy->force);
        self::assertSame(StatementKind::Drop, $copy->kind);
    }

    public function testWithNameReplacesTheRemovedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DATABASE app');
        self::assertInstanceOf(DropDatabaseStatement::class, $statement);
        self::assertSame('DROP DATABASE "other"', $statement->withName('other')->toString());
    }

    public function testWithIfExistsSelectsTheMissingDatabasePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DATABASE app');
        self::assertInstanceOf(DropDatabaseStatement::class, $statement);
        self::assertSame('DROP DATABASE IF EXISTS "app"', $statement->withIfExists(true)->toString());
        self::assertFalse($statement->ifExists);
    }

    public function testWithForceSelectsSessionTermination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP DATABASE app WITH (FORCE)');
        self::assertInstanceOf(DropDatabaseStatement::class, $statement);
        self::assertSame('DROP DATABASE "app"', $statement->withForce(false)->toString());
        self::assertTrue($statement->force);
    }
}
