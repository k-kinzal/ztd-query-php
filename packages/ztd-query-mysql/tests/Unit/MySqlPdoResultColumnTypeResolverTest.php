<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlPdoResultColumnTypeResolver;
use ZtdQuery\Platform\MySql\MySqlColumnTypeMapper;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlPdoResultColumnTypeResolver::class)]
#[UsesClass(MySqlColumnTypeMapper::class)]
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

    public function testResolvesEveryPreviouslyMissingPdoMySqlNativeType(): void
    {
        $resolver = new MySqlPdoResultColumnTypeResolver();

        self::assertSame(ColumnTypeFamily::INTEGER, $resolver->resolve(['native_type' => 'TINY'])->family);
        self::assertSame(ColumnTypeFamily::BINARY, $resolver->resolve(['native_type' => 'TINY_BLOB'])->family);
        self::assertSame(ColumnTypeFamily::BINARY, $resolver->resolve(['native_type' => 'MEDIUM_BLOB'])->family);
        self::assertSame(ColumnTypeFamily::BINARY, $resolver->resolve(['native_type' => 'LONG_BLOB'])->family);
    }
}
