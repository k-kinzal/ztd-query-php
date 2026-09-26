<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogColumn as Subject;
use SqlFixture\Platform\PostgreSql\Schema\CatalogExpression;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NumericLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(CatalogExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\DefaultExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\StringLiteral::class)]
final class CatalogColumnTest extends TestCase
{
    public function testResolveTypeUppercasesAndExpandsArraysAndUserTypes(): void
    {
        self::assertSame('CHARACTER VARYING', (new Subject())->resolveType(['data_type' => 'character varying', 'udt_name' => 'varchar']));
        self::assertSame('INT4_ARRAY', (new Subject())->resolveType(['data_type' => 'ARRAY', 'udt_name' => '_int4']));
        self::assertSame('MOOD', (new Subject())->resolveType(['data_type' => 'USER-DEFINED', 'udt_name' => 'mood']));
    }

    public function testParseReadsDimensionsNullabilityAndLiteralDefaults(): void
    {
        $row = ['column_name' => 'name', 'data_type' => 'character varying', 'character_maximum_length' => '30', 'numeric_precision' => null, 'numeric_scale' => null, 'is_nullable' => 'YES', 'column_default' => "'ready'::character varying", 'udt_name' => 'varchar', 'is_identity' => 'NO', 'is_generated' => 'NEVER'];
        $column = (new Subject())->parse($row, new CatalogExpression(new PostgreSqlParser()), false);

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
        $column = (new Subject())->parse($row, new CatalogExpression(new PostgreSqlParser()), true);

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
        $expressions = new CatalogExpression(new PostgreSqlParser());

        self::assertTrue((new Subject())->parse($identity, $expressions, false)->autoIncrement);
        self::assertNull((new Subject())->parse($identity, $expressions, false)->default);
        self::assertTrue((new Subject())->parse($generated, $expressions, false)->generated);
        self::assertFalse((new Subject())->parse($generated, $expressions, false)->autoIncrement);
        self::assertFalse((new Subject())->parse($generated, $expressions, true)->nullable);
    }
}
