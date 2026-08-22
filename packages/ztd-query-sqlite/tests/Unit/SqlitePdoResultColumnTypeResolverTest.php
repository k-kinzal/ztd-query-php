<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqlitePdoResultColumnTypeResolver;
use ZtdQuery\Platform\Sqlite\SqliteColumnTypeMapper;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(SqlitePdoResultColumnTypeResolver::class)]
#[UsesClass(SqliteColumnTypeMapper::class)]
final class SqlitePdoResultColumnTypeResolverTest extends TestCase
{
    public function testPrefersSqliteDeclaredType(): void
    {
        $type = (new SqlitePdoResultColumnTypeResolver())->resolve([
            'sqlite:decl_type' => 'REAL',
            'native_type' => 'null',
        ]);

        self::assertSame(ColumnTypeFamily::FLOAT, $type->family);
        self::assertSame('REAL', $type->nativeType);
    }

    public function testFallsBackToNativeType(): void
    {
        $type = (new SqlitePdoResultColumnTypeResolver())->resolve(['native_type' => 'integer']);

        self::assertSame(ColumnTypeFamily::INTEGER, $type->family);
        self::assertSame('integer', $type->nativeType);
    }
}
