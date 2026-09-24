<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\SetDatabaseTablespaceStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetDatabaseTablespaceStatement::class)]
#[Medium]
final class SetDatabaseTablespaceStatementTest extends TestCase
{
    public function testWithOriginRetainsTheMove(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app WITH TABLESPACE fast');
        self::assertInstanceOf(SetDatabaseTablespaceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame('fast', $copy->tablespace);
        self::assertSame('ALTER DATABASE "app" SET TABLESPACE "fast"', $copy->toString());
    }

    public function testWithNameReplacesTheMovedDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET TABLESPACE fast');
        self::assertInstanceOf(SetDatabaseTablespaceStatement::class, $statement);
        self::assertSame('other', $statement->withName('other')->name);
        self::assertSame('app', $statement->name);
    }

    public function testWithTablespaceReplacesTheDestination(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET TABLESPACE fast');
        self::assertInstanceOf(SetDatabaseTablespaceStatement::class, $statement);
        self::assertSame('slow', $statement->withTablespace('slow')->tablespace);
        $this->expectException(InvalidStructure::class);
        $statement->withTablespace('');
    }
}
