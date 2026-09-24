<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace\DropTablespaceStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropTablespaceStatement::class)]
#[Medium]
final class DropTablespaceStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRemoval(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TABLESPACE IF EXISTS fast');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertTrue($copy->ifExists);
        self::assertSame(StatementKind::Drop, $copy->kind);
    }

    public function testWithNameReplacesTheTablespace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TABLESPACE fast');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        self::assertSame('DROP TABLESPACE "slow"', $statement->withName('slow')->toString());
    }

    public function testWithIfExistsSelectsTheMissingTablespacePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP TABLESPACE fast');
        self::assertInstanceOf(DropTablespaceStatement::class, $statement);
        self::assertSame('DROP TABLESPACE IF EXISTS "fast"', $statement->withIfExists(true)->toString());
    }
}
