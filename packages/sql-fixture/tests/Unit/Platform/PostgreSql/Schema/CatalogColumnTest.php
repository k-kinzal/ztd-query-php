<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogColumn as Subject;

#[CoversClass(Subject::class)]
final class CatalogColumnTest extends TestCase
{
    public function testMapDataTypeFormatsNumericAndLengthParameters(): void
    {
        $column = ['data_type' => 'numeric', 'character_maximum_length' => null, 'numeric_precision' => '8', 'numeric_scale' => '2', 'udt_name' => 'numeric'];
        self::assertSame('NUMERIC(8, 2)', (new Subject())->mapDataType($column));
        $column['data_type'] = 'character varying';
        $column['character_maximum_length'] = '40';
        self::assertSame('VARCHAR(40)', (new Subject())->mapDataType($column));
    }

    public function testResolveTypeNormalizesArrayElements(): void
    {
        $column = ['data_type' => 'ARRAY', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null, 'udt_name' => '_int4'];
        self::assertSame('INT4_ARRAY', (new Subject())->resolveType($column));
    }

    public function testParseDefaultRemovesCastsAndRecognizesSequences(): void
    {
        $parser = new Subject();
        self::assertSame('open', $parser->parseDefault("'open'::text"));
        self::assertNull($parser->parseDefault("nextval('users_id_seq'::regclass)"));
        self::assertSame(12.5, $parser->parseDefault('12.5'));
        self::assertTrue($parser->parseDefault('true'));
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCatalogTypes')]
    public function testMapDataTypeRetainsTheCatalogDeclaration(string $type, ?string $length, ?string $precision, ?string $scale, string $udt, string $ddl, string $resolved): void
    {
        $row = ['data_type' => $type, 'character_maximum_length' => $length, 'numeric_precision' => $precision, 'numeric_scale' => $scale, 'udt_name' => $udt];
        $column = new Subject();
        self::assertSame($ddl, $column->mapDataType($row));
        self::assertSame($resolved, $column->resolveType($row));
    }

    /**
     * @return list<array{string, ?string, ?string, ?string, string, string, string}>
     */
    public static function providerCatalogTypes(): array
    {
        return [
            ['character varying', '40', null, null, 'varchar', 'VARCHAR(40)', 'CHARACTER VARYING'],
            ['character varying', null, null, null, 'varchar', 'CHARACTER VARYING', 'CHARACTER VARYING'],
            ['character', '3', null, null, 'bpchar', 'CHAR(3)', 'CHARACTER'],
            ['character', null, null, null, 'bpchar', 'CHARACTER', 'CHARACTER'],
            ['numeric', null, null, null, 'numeric', 'NUMERIC', 'NUMERIC'],
            ['numeric', null, '8', null, 'numeric', 'NUMERIC(8)', 'NUMERIC'],
            ['numeric', null, '8', '0', 'numeric', 'NUMERIC(8)', 'NUMERIC'],
            ['numeric', null, '8', '2', 'numeric', 'NUMERIC(8, 2)', 'NUMERIC'],
            ['array', null, null, null, '_int4', '_INT4', 'INT4_ARRAY'],
            ['user-defined', null, null, null, 'mood', 'MOOD', 'MOOD'],
            ['integer', null, '32', '0', 'int4', 'INTEGER', 'INTEGER'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerCatalogDefaults')]
    public function testParseDefaultInterpretsCatalogLiterals(?string $input, int|float|bool|string|null $expected): void
    {
        self::assertSame($expected, (new Subject())->parseDefault($input));
    }

    /**
     * @return list<array{?string, int|float|bool|string|null}>
     */
    public static function providerCatalogDefaults(): array
    {
        return [
            [null, null],
            ['null', null],
            ['null::', null],
            ["nextval('orders_id_seq'::regclass)", null],
            ["'line\nvalue'::text", "line\nvalue"],
            ["'ready'", 'ready'],
            ["'line\nvalue'", "line\nvalue"],
            ['FALSE', false],
            ['TRUE', true],
            ['0', 0],
            ['-12', -12],
            ['-0.25', -0.25],
            ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
        ];
    }
}
