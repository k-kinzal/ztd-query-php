<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlPdoResultColumnTypeResolver;
use ZtdQuery\Platform\Postgres\PgSqlColumnTypeMapper;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(PgSqlPdoResultColumnTypeResolver::class)]
#[UsesClass(PgSqlColumnTypeMapper::class)]
#[UsesClass(ColumnType::class)]
final class PgSqlPdoResultColumnTypeResolverTest extends TestCase
{
    public function testRestoresPostgreSqlCharacterTypeLength(): void
    {
        $resolver = new PgSqlPdoResultColumnTypeResolver();

        $varchar = $resolver->resolve(['native_type' => 'varchar', 'len' => 30]);
        $char = $resolver->resolve(['native_type' => 'bpchar', 'len' => 4]);

        self::assertSame(ColumnTypeFamily::STRING, $varchar->family);
        self::assertSame('VARCHAR(30)', $varchar->nativeType);
        self::assertSame(ColumnTypeFamily::STRING, $char->family);
        self::assertSame('CHAR(4)', $char->nativeType);
    }

    public function testPreservesTypesWithoutApplicableLength(): void
    {
        $resolver = new PgSqlPdoResultColumnTypeResolver();

        self::assertSame('int4', $resolver->resolve(['native_type' => 'int4', 'len' => 4])->nativeType);
        self::assertSame('VARCHAR(8)', $resolver->resolve(['native_type' => 'VARCHAR(8)', 'len' => 20])->nativeType);
        self::assertSame('', $resolver->resolve(['native_type' => null])->nativeType);
    }
}
