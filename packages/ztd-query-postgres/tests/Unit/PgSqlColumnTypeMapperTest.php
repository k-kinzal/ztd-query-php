<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlColumnTypeMapper;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(PgSqlColumnTypeMapper::class)]
final class PgSqlColumnTypeMapperTest extends TestCase
{
    public function testMapsSchemaAndPdoNativeTypes(): void
    {
        $mapper = new PgSqlColumnTypeMapper();

        self::assertSame(ColumnTypeFamily::INTEGER, $mapper->map('int4')->family);
        self::assertSame(ColumnTypeFamily::DOUBLE, $mapper->map('float8')->family);
        self::assertSame(ColumnTypeFamily::STRING, $mapper->map('bpchar')->family);
        self::assertSame(ColumnTypeFamily::TEXT, $mapper->map('citext')->family);
        self::assertSame(ColumnTypeFamily::BINARY, $mapper->map('bytea')->family);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $mapper->map('custom_domain')->family);
    }

    public function testMapsArrayByItsElementTypeAndPreservesNativeType(): void
    {
        $type = (new PgSqlColumnTypeMapper())->map('numeric(10, 2)[][]');

        self::assertSame(ColumnTypeFamily::DECIMAL, $type->family);
        self::assertSame('numeric(10, 2)[][]', $type->nativeType);
    }
}
