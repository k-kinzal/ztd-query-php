<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlColumnTypeMapper;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlColumnTypeMapper::class)]
final class MySqlColumnTypeMapperTest extends TestCase
{
    public function testMapsSchemaAndPdoNativeTypes(): void
    {
        $mapper = new MySqlColumnTypeMapper();

        self::assertSame(ColumnTypeFamily::INTEGER, $mapper->map('INT')->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $mapper->map('LONGLONG')->family);
        self::assertSame(ColumnTypeFamily::DECIMAL, $mapper->map('NEWDECIMAL')->family);
        self::assertSame(ColumnTypeFamily::STRING, $mapper->map('VARCHAR(32)')->family);
        self::assertSame(ColumnTypeFamily::BINARY, $mapper->map('BLOB')->family);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $mapper->map('GEOMETRY')->family);
    }

    public function testPreservesNativeType(): void
    {
        self::assertSame('decimal(10, 2)', (new MySqlColumnTypeMapper())->map('decimal(10, 2)')->nativeType);
    }
}
