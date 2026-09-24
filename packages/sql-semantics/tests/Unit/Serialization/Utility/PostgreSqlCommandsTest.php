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
    #[TestWith(["CREATE TABLESPACE ts LOCATION '/x'", 'CREATE TABLESPACE "ts" LOCATION \'/x\''])]
    #[TestWith(['CREATE SCHEMA s', 'CREATE SCHEMA "s"'])]
    #[TestWith(["ALTER SYSTEM SET work_mem = '1MB'", 'ALTER SYSTEM SET "work_mem" = \'1MB\''])]
    #[TestWith(['SHOW work_mem', 'SHOW "work_mem"'])]
    #[TestWith(['VACUUM t', 'VACUUM "public"."t"'])]
    #[TestWith(['COPY t FROM STDIN', 'COPY "public"."t" FROM STDIN'])]
    public function testWriteRoutesEachUtilityFamily(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind($sql);
        self::assertSame($expected, PostgreSqlCommands::write($statement)?->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(PostgreSqlCommands::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }
}
