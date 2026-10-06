<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogColumn as Subject;
use SqlFixture\Platform\PostgreSql\Schema\CatalogExpression;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Analysis\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CatalogExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
final class CatalogColumnTest extends TestCase
{
    public function testResolveTypeUppercasesAndExpandsArraysAndUserTypes(): void
    {
        self::assertSame('CHARACTER VARYING', (new Subject())->resolveType(['data_type' => 'character varying', 'udt_name' => 'varchar']));
        self::assertSame('INTEGER_ARRAY', (new Subject())->resolveType(['data_type' => 'ARRAY', 'udt_name' => '_int4']));
        self::assertSame('TEXT_ARRAY', (new Subject())->resolveType(['data_type' => 'ARRAY', 'udt_name' => '_text']));
        self::assertSame('MOOD', (new Subject())->resolveType(['data_type' => 'USER-DEFINED', 'udt_name' => 'mood']));
    }

    public function testParseReadsDimensionsNullabilityAndLiteralDefaults(): void
    {
        $row = ['column_name' => 'name', 'data_type' => 'character varying', 'character_maximum_length' => '30', 'numeric_precision' => null, 'numeric_scale' => null, 'is_nullable' => 'YES', 'column_default' => "'ready'::character varying", 'udt_name' => 'varchar', 'is_identity' => 'NO', 'is_generated' => 'NEVER'];
        $column = (new Subject())->parse($row, new CatalogExpression(\Tests\Statement\PostgreSqlStatements::semantics()), false);

        self::assertSame('name', $column->name);
        self::assertSame('CHARACTER VARYING', $column->type);
        self::assertSame(30, $column->length);
        self::assertNull($column->precision);
        self::assertNull($column->scale);
        self::assertTrue($column->nullable);
        self::assertFalse($column->unsigned);
        self::assertSame('ready', $column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->generated);
        self::assertNull($column->enumValues);
    }

    public function testParseMarksSequenceDefaultsAsAutoIncrementWithoutADefault(): void
    {
        $row = ['column_name' => 'id', 'data_type' => 'integer', 'character_maximum_length' => null, 'numeric_precision' => '32', 'numeric_scale' => '0', 'is_nullable' => 'NO', 'column_default' => "nextval('users_id_seq'::regclass)", 'udt_name' => 'int4', 'is_identity' => 'NO', 'is_generated' => 'NEVER'];
        $column = (new Subject())->parse($row, new CatalogExpression(\Tests\Statement\PostgreSqlStatements::semantics()), true);

        self::assertTrue($column->autoIncrement);
        self::assertNull($column->default);
        self::assertFalse($column->nullable);
        self::assertSame(32, $column->precision);
        self::assertSame(0, $column->scale);
    }

    public function testParseMarksIdentityGeneratedAndPrimaryKeyColumns(): void
    {
        $identity = ['column_name' => 'id', 'data_type' => 'bigint', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null, 'is_nullable' => 'NO', 'column_default' => null, 'udt_name' => 'int8', 'is_identity' => 'YES', 'is_generated' => 'NEVER'];
        $generated = ['column_name' => 'g', 'data_type' => 'integer', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null, 'is_nullable' => 'YES', 'column_default' => null, 'udt_name' => 'int4', 'is_identity' => 'NO', 'is_generated' => 'ALWAYS'];
        $expressions = new CatalogExpression(\Tests\Statement\PostgreSqlStatements::semantics());

        self::assertTrue((new Subject())->parse($identity, $expressions, false)->autoIncrement);
        self::assertNull((new Subject())->parse($identity, $expressions, false)->default);
        self::assertTrue((new Subject())->parse($generated, $expressions, false)->generated);
        self::assertFalse((new Subject())->parse($generated, $expressions, false)->autoIncrement);
        self::assertFalse((new Subject())->parse($generated, $expressions, true)->nullable);
    }

    public function testElementTypeNamesTheDeclaredType(): void
    {
        $column = new Subject();
        self::assertSame('INTEGER', $column->elementType('_int4'));
        self::assertSame('BIGINT', $column->elementType('_int8'));
        self::assertSame('SMALLINT', $column->elementType('_int2'));
        self::assertSame('REAL', $column->elementType('_float4'));
        self::assertSame('DOUBLE PRECISION', $column->elementType('_float8'));
        self::assertSame('BOOLEAN', $column->elementType('_bool'));
        self::assertSame('VARCHAR', $column->elementType('_varchar'));
        self::assertSame('CHAR', $column->elementType('_bpchar'));
        self::assertSame('TEXT', $column->elementType('_text'));
        self::assertSame('UUID', $column->elementType('_uuid'));
    }
}
