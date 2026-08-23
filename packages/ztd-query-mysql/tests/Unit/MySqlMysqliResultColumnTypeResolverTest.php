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

    public function testResolvesEverySupportedMysqliProtocolTypeCode(): void
    {
        $nativeTypes = [
            0 => 'DECIMAL',
            1 => 'TINYINT',
            2 => 'SMALLINT',
            3 => 'INTEGER',
            4 => 'FLOAT',
            5 => 'DOUBLE',
            6 => 'NULL',
            7 => 'TIMESTAMP',
            8 => 'BIGINT',
            9 => 'MEDIUMINT',
            10 => 'DATE',
            11 => 'TIME',
            12 => 'DATETIME',
            13 => 'YEAR',
            14 => 'DATE',
            15 => 'VARCHAR',
            16 => 'BIT',
            17 => 'TIMESTAMP',
            18 => 'DATETIME',
            19 => 'TIME',
            242 => 'VECTOR',
            245 => 'JSON',
            246 => 'DECIMAL',
            247 => 'ENUM',
            248 => 'SET',
            253 => 'VARCHAR',
            254 => 'CHAR',
            255 => 'GEOMETRY',
        ];
        $families = array_replace(
            array_fill_keys([1, 2, 3, 8, 9, 13, 16], ColumnTypeFamily::INTEGER),
            array_fill_keys([4], ColumnTypeFamily::FLOAT),
            array_fill_keys([5], ColumnTypeFamily::DOUBLE),
            array_fill_keys([0, 246], ColumnTypeFamily::DECIMAL),
            array_fill_keys([10, 14], ColumnTypeFamily::DATE),
            array_fill_keys([11, 19], ColumnTypeFamily::TIME),
            array_fill_keys([12, 18], ColumnTypeFamily::DATETIME),
            array_fill_keys([7, 17], ColumnTypeFamily::TIMESTAMP),
            array_fill_keys([245], ColumnTypeFamily::JSON),
            array_fill_keys([242], ColumnTypeFamily::BINARY),
            array_fill_keys([15, 247, 248, 253, 254], ColumnTypeFamily::STRING),
            array_fill_keys([6, 255], ColumnTypeFamily::UNKNOWN),
        );
        $resolver = new MySqlMysqliResultColumnTypeResolver();
        $types = array_keys($nativeTypes);
        $resolved = array_map(
            static fn (int $type): ColumnType => $resolver->resolve(['type' => $type]),
            $types,
        );

        self::assertSame(
            array_map(static fn (int $type): ColumnTypeFamily => $families[$type], $types),
            array_map(static fn (ColumnType $type): ColumnTypeFamily => $type->family, $resolved),
        );
        self::assertSame(
            array_values($nativeTypes),
            array_map(static fn (ColumnType $type): string => $type->nativeType, $resolved),
        );
    }

    public function testResolvesEveryBinaryCapableProtocolTypeCodeByCharset(): void
    {
        $types = [249, 250, 251, 252, 253, 254];
        $resolver = new MySqlMysqliResultColumnTypeResolver();
        $binary = array_map(
            static fn (int $type): ColumnType => $resolver->resolve([
                'type' => $type,
                'charsetnr' => '63',
            ]),
            $types,
        );
        $text = array_map(
            static fn (int $type): ColumnType => $resolver->resolve([
                'type' => $type,
                'charsetnr' => 255,
            ]),
            $types,
        );

        self::assertSame(
            array_fill(0, count($types), ColumnTypeFamily::BINARY),
            array_map(static fn (ColumnType $type): ColumnTypeFamily => $type->family, $binary),
        );
        self::assertSame(
            ['TINYBLOB', 'MEDIUMBLOB', 'LONGBLOB', 'BLOB', 'VARBINARY', 'BINARY'],
            array_map(static fn (ColumnType $type): string => $type->nativeType, $binary),
        );
        self::assertSame(
            [
                ColumnTypeFamily::TEXT,
                ColumnTypeFamily::TEXT,
                ColumnTypeFamily::TEXT,
                ColumnTypeFamily::TEXT,
                ColumnTypeFamily::STRING,
                ColumnTypeFamily::STRING,
            ],
            array_map(static fn (ColumnType $type): ColumnTypeFamily => $type->family, $text),
        );
        self::assertSame(
            ['TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'TEXT', 'VARCHAR', 'CHAR'],
            array_map(static fn (ColumnType $type): string => $type->nativeType, $text),
        );
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
        self::assertSame(ColumnTypeFamily::UNKNOWN, $resolver->resolve(['type' => 256])->family);
    }
}
