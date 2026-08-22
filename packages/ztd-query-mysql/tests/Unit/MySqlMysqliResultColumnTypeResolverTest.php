<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlColumnTypeMapper;
use ZtdQuery\Platform\MySql\MySqlMysqliResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlMysqliResultColumnTypeResolver::class)]
#[UsesClass(MySqlColumnTypeMapper::class)]
#[UsesClass(ColumnType::class)]
final class MySqlMysqliResultColumnTypeResolverTest extends TestCase
{
    public function testResolvesMysqliProtocolTypes(): void
    {
        $resolver = new MySqlMysqliResultColumnTypeResolver();

        self::assertSame(ColumnTypeFamily::INTEGER, $resolver->resolve(['type' => MYSQLI_TYPE_LONG])->family);
        self::assertSame(ColumnTypeFamily::DECIMAL, $resolver->resolve(['type' => MYSQLI_TYPE_NEWDECIMAL])->family);
        self::assertSame(ColumnTypeFamily::JSON, $resolver->resolve(['type' => MYSQLI_TYPE_JSON])->family);
        self::assertSame(ColumnTypeFamily::STRING, $resolver->resolve(['type' => MYSQLI_TYPE_VAR_STRING])->family);
    }

    public function testUsesCharsetToDistinguishBinaryAndTextBlobs(): void
    {
        $resolver = new MySqlMysqliResultColumnTypeResolver();

        self::assertSame(
            ColumnTypeFamily::BINARY,
            $resolver->resolve(['type' => MYSQLI_TYPE_BLOB, 'charsetnr' => 63])->family,
        );
        self::assertSame(
            ColumnTypeFamily::TEXT,
            $resolver->resolve(['type' => MYSQLI_TYPE_BLOB, 'charsetnr' => '255'])->family,
        );
    }

    public function testTreatsInvalidMetadataAsUnknown(): void
    {
        $resolver = new MySqlMysqliResultColumnTypeResolver();

        self::assertSame(ColumnTypeFamily::UNKNOWN, $resolver->resolve([])->family);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $resolver->resolve(['type' => '3'])->family);
        self::assertSame(ColumnTypeFamily::UNKNOWN, $resolver->resolve(['type' => MYSQLI_TYPE_NULL])->family);
    }
}
