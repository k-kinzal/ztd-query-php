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

    public function testMapsEverySupportedNativeTypeAlias(): void
    {
        $families = array_merge(
            array_fill_keys(['INT', 'INTEGER', 'TINY', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT', 'LONG', 'LONGLONG', 'SHORT', 'INT24', 'YEAR', 'BIT'], ColumnTypeFamily::INTEGER),
            array_fill_keys(['FLOAT'], ColumnTypeFamily::FLOAT),
            array_fill_keys(['DOUBLE', 'REAL'], ColumnTypeFamily::DOUBLE),
            array_fill_keys(['DECIMAL', 'NEWDECIMAL', 'NUMERIC'], ColumnTypeFamily::DECIMAL),
            array_fill_keys(['CHAR', 'VARCHAR', 'VAR_STRING', 'STRING', 'ENUM', 'SET'], ColumnTypeFamily::STRING),
            array_fill_keys(['TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT'], ColumnTypeFamily::TEXT),
            array_fill_keys(['BOOL', 'BOOLEAN'], ColumnTypeFamily::BOOLEAN),
            array_fill_keys(['DATE', 'NEWDATE'], ColumnTypeFamily::DATE),
            array_fill_keys(['TIME', 'TIME2'], ColumnTypeFamily::TIME),
            array_fill_keys(['DATETIME', 'DATETIME2'], ColumnTypeFamily::DATETIME),
            array_fill_keys(['TIMESTAMP', 'TIMESTAMP2'], ColumnTypeFamily::TIMESTAMP),
            array_fill_keys(['BINARY', 'VARBINARY', 'BLOB', 'TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'TINY_BLOB', 'MEDIUM_BLOB', 'LONG_BLOB', 'VECTOR'], ColumnTypeFamily::BINARY),
            array_fill_keys(['JSON'], ColumnTypeFamily::JSON),
        );
        $mapper = new MySqlColumnTypeMapper();

        self::assertSame(
            array_values($families),
            array_map(static fn (string $nativeType): ColumnTypeFamily => $mapper->map($nativeType)->family, array_keys($families)),
        );
        self::assertSame(ColumnTypeFamily::STRING, $mapper->map(' varchar(32) ')->family);
    }

    public function testPreservesNativeType(): void
    {
        self::assertSame('decimal(10, 2)', (new MySqlColumnTypeMapper())->map('decimal(10, 2)')->nativeType);
    }

    public function testNormalizesParametersAndTypeAttributes(): void
    {
        $mapper = new MySqlColumnTypeMapper();

        self::assertSame(ColumnTypeFamily::INTEGER, $mapper->map('INT(10) UNSIGNED')->family);
        self::assertSame(ColumnTypeFamily::INTEGER, $mapper->map(' BIGINT UNSIGNED ')->family);
        self::assertSame(ColumnTypeFamily::DOUBLE, $mapper->map('DOUBLE PRECISION')->family);
        self::assertSame(ColumnTypeFamily::DECIMAL, $mapper->map("DECIMAL\t(10, 2) UNSIGNED")->family);
    }
}
