<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlTableDeclaration;

#[CoversClass(PostgreSqlTableDeclaration::class)]
final class PostgreSqlTableDeclarationTest extends TestCase
{
    public function testOfWritesTheStatementThatWouldDeclareWhatTheCatalogDescribed(): void
    {
        self::assertSame(
            'CREATE TABLE "users" ("id" INTEGER NOT NULL, PRIMARY KEY ("id"))',
            (new PostgreSqlTableDeclaration())->of('users', [[
                'column_name' => 'id',
                'data_type' => 'integer',
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'NO',
                'column_default' => null,
                'udt_name' => 'int4',
            ]], ['id']),
        );
    }

    public function testOfCarriesADefaultThroughAsTheCatalogReportedIt(): void
    {
        self::assertStringContainsString(
            "DEFAULT nextval('users_id_seq'::regclass)",
            (new PostgreSqlTableDeclaration())->of('users', [[
                'column_name' => 'id',
                'data_type' => 'integer',
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'NO',
                'column_default' => "nextval('users_id_seq'::regclass)",
                'udt_name' => 'int4',
            ]], []),
        );
    }

    public function testOfLeavesOutAPrimaryKeyClauseWhereThereIsNoKey(): void
    {
        self::assertStringNotContainsString(
            'PRIMARY KEY',
            (new PostgreSqlTableDeclaration())->of('users', [[
                'column_name' => 'id',
                'data_type' => 'integer',
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_nullable' => 'YES',
                'column_default' => null,
                'udt_name' => 'int4',
            ]], []),
        );
    }

    #[DataProvider('providerCatalogType')]
    public function testTypeOfPutsBackWhatTheCatalogReportsBesideTheType(
        string $dataType,
        ?string $length,
        ?string $precision,
        ?string $scale,
        string $udtName,
        string $expected,
    ): void {
        self::assertSame($expected, (new PostgreSqlTableDeclaration())->typeOf([
            'data_type' => $dataType,
            'character_maximum_length' => $length,
            'numeric_precision' => $precision,
            'numeric_scale' => $scale,
            'udt_name' => $udtName,
        ]));
    }

    /**
     * @return iterable<string, array{string, string|null, string|null, string|null, string, string}>
     */
    public static function providerCatalogType(): iterable
    {
        yield 'varying with a length' => ['character varying', '9', null, null, 'varchar', 'VARCHAR(9)'];
        yield 'varying with none' => ['character varying', null, null, null, 'varchar', 'CHARACTER VARYING'];
        yield 'character with a length' => ['character', '4', null, null, 'bpchar', 'CHAR(4)'];
        yield 'numeric with both' => ['numeric', null, '10', '2', 'numeric', 'NUMERIC(10, 2)'];
        yield 'numeric with no scale' => ['numeric', null, '10', '0', 'numeric', 'NUMERIC(10)'];
        yield 'an array' => ['ARRAY', null, null, null, '_int4', '_INT4'];
        yield 'a user defined type' => ['USER-DEFINED', null, null, null, 'mood', 'MOOD'];
        yield 'anything else' => ['integer', null, null, null, 'int4', 'INTEGER'];
    }
}
