<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlPdoResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlColumnTypeMapper;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlPdoResultColumnTypeResolver::class)]
#[UsesClass(MySqlColumnTypeMapper::class)]
#[UsesClass(ColumnType::class)]
final class MySqlPdoResultColumnTypeResolverTest extends TestCase
{
    public function testResolvesPdoMySqlNativeType(): void
    {
        $type = (new MySqlPdoResultColumnTypeResolver())->resolve(['native_type' => 'LONGLONG']);

        self::assertSame(ColumnTypeFamily::INTEGER, $type->family);
        self::assertSame('LONGLONG', $type->nativeType);
    }

    public function testTreatsInvalidMetadataAsUnknown(): void
    {
        $type = (new MySqlPdoResultColumnTypeResolver())->resolve(['native_type' => null]);

        self::assertSame(ColumnTypeFamily::UNKNOWN, $type->family);
        self::assertSame('', $type->nativeType);
    }
}
