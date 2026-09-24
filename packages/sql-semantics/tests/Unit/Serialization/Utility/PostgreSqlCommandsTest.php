<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Utility\PostgreSqlCommands;

#[CoversClass(PostgreSqlCommands::class)]
#[Medium]
final class PostgreSqlCommandsTest extends TestCase
{
    #[TestWith(['CREATE DATABASE app', 'CREATE DATABASE "app"'])]
    public function testWriteRoutesEachUtilityFamily(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertSame($expected, PostgreSqlCommands::write($statement)?->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(PostgreSqlCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }
}
