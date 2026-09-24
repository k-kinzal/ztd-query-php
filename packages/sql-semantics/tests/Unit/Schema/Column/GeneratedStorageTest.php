<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\ComputedColumn;
use SqlSemantics\Schema\Column\GeneratedStorage;
use SqlSemantics\SchemaBuilder;

#[CoversClass(GeneratedStorage::class)]
#[Medium]
final class GeneratedStorageTest extends TestCase
{
    public function testRepresentsBothStoragePolicies(): void
    {
        self::assertSame(['virtual', 'stored'], array_column(GeneratedStorage::cases(), 'value'));
    }

    public function testMySqlDefaultsToVirtualAndPostgreSqlToStored(): void
    {
        $mysql = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT AS (a + 1))')->tables[0]->columns[1];
        $postgres = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER GENERATED ALWAYS AS (a + 1) STORED)')->tables[0]->columns[1];
        self::assertInstanceOf(ComputedColumn::class, $mysql->generation);
        self::assertInstanceOf(ComputedColumn::class, $postgres->generation);
        self::assertSame(GeneratedStorage::Virtual, $mysql->generation->storage);
        self::assertSame(GeneratedStorage::Stored, $postgres->generation->storage);
    }
}
