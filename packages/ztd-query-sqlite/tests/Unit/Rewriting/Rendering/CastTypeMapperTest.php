<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Rewriting\Rendering\CastTypeMapper;

#[CoversClass(CastTypeMapper::class)]
final class CastTypeMapperTest extends TestCase
{
    public function testMapToCastTypeUsesPortableFamilies(): void
    {
        $mapper = new CastTypeMapper();
        self::assertSame('INTEGER', $mapper->mapToCastType(new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::BOOLEAN, 'BOOL')));
        self::assertSame('NUMERIC', $mapper->mapToCastType(new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::DECIMAL, 'DECIMAL(8,2)')));
        self::assertSame('BLOB', $mapper->mapToCastType(new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::UNKNOWN, 'BLOB')));
    }

    public function testMapNativeTypeToCastTypeRemovesPrecision(): void
    {
        $mapper = new CastTypeMapper();
        self::assertSame('INTEGER', $mapper->mapNativeTypeToCastType('int(11)'));
        self::assertSame('NUMERIC', $mapper->mapNativeTypeToCastType('decimal(10,2)'));
        self::assertSame('REAL', $mapper->mapNativeTypeToCastType('double'));
        self::assertSame('TEXT', $mapper->mapNativeTypeToCastType('uuid'));
    }

}
