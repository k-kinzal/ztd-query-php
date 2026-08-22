<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteColumnTypeMapper;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(SqliteColumnTypeMapper::class)]
final class SqliteColumnTypeMapperTest extends TestCase
{
    public function testMapsSchemaAndPdoNativeTypes(): void
    {
        $mapper = new SqliteColumnTypeMapper();

        self::assertSame(ColumnTypeFamily::INTEGER, $mapper->map('integer')->family);
        self::assertSame(ColumnTypeFamily::FLOAT, $mapper->map('DOUBLE')->family);
        self::assertSame(ColumnTypeFamily::STRING, $mapper->map('VARCHAR(32)')->family);
        self::assertSame(ColumnTypeFamily::TEXT, $mapper->map('CLOB')->family);
        self::assertSame(ColumnTypeFamily::BINARY, $mapper->map('BLOB')->family);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $mapper->map('null')->family);
    }

    public function testPreservesNativeType(): void
    {
        self::assertSame('varchar(32)', (new SqliteColumnTypeMapper())->map('varchar(32)')->nativeType);
    }
}
