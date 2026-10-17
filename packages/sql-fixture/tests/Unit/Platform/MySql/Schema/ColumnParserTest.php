<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\MySql\Schema\ColumnAttributes;
use SqlFixture\Platform\MySql\Schema\ColumnParser as Subject;
use SqlFixture\Platform\MySql\Schema\DefaultExpression;
use SqlFixture\Platform\MySql\Schema\TypeParameters;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TypeShape;
use Tests\Statement\MySqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(ColumnAttributes::class)]
#[UsesClass(DefaultExpression::class)]
#[UsesClass(TypeParameters::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseReadsTypeAttributesAndDefault(): void
    {
        $column = MySqlStatements::parsedColumns("CREATE TABLE t (`amount` DECIMAL(8, 2) UNSIGNED NOT NULL DEFAULT 12.5 COMMENT 'money')")['amount'];

        self::assertSame('DECIMAL', $column->type);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertNull($column->length);
        self::assertFalse($column->nullable);
        self::assertTrue($column->unsigned);
        self::assertSame(12.5, $column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->generated);
        self::assertNull($column->enumValues);
    }

    public function testParseReadsEnumValuesAndKeyNullability(): void
    {
        $columns = MySqlStatements::parsedColumns("CREATE TABLE t (status ENUM('a', 'b') DEFAULT 'a', id INT AUTO_INCREMENT, k INT, other INT, UNIQUE (id))", ['k']);

        self::assertSame(['a', 'b'], $columns['status']->enumValues);
        self::assertSame('a', $columns['status']->default);
        self::assertTrue($columns['status']->nullable);
        self::assertTrue($columns['id']->autoIncrement);
        self::assertFalse($columns['id']->nullable);
        self::assertFalse($columns['k']->nullable);
        self::assertTrue($columns['other']->nullable);
        self::assertFalse($columns['other']->unsigned);
    }

    public function testParseTakesNullabilityFromTheAnalysis(): void
    {
        $columns = MySqlStatements::parsedColumns('CREATE TABLE t (a INT NOT NULL NULL, b INT NULL NOT NULL, c INT)');

        self::assertTrue($columns['a']->nullable);
        self::assertFalse($columns['b']->nullable);
        self::assertTrue($columns['c']->nullable);
    }

    public function testParseMarksGeneratedColumns(): void
    {
        $columns = MySqlStatements::parsedColumns('CREATE TABLE t (a INT, b INT GENERATED ALWAYS AS (a + 1) STORED, c INT AS (a) VIRTUAL NOT NULL)');

        self::assertFalse($columns['a']->generated);
        self::assertTrue($columns['b']->generated);
        self::assertTrue($columns['b']->nullable);
        self::assertTrue($columns['c']->generated);
        self::assertFalse($columns['c']->nullable);
    }

    public function testParseTreatsSerialAsUnsignedAutoIncrement(): void
    {
        $column = MySqlStatements::parsedColumns('CREATE TABLE t (id SERIAL)')['id'];

        self::assertSame('BIGINT', $column->type);
        self::assertTrue($column->unsigned);
        self::assertTrue($column->autoIncrement);
        self::assertFalse($column->nullable);
    }

    public function testParseReadsARadixDefaultByTheColumnType(): void
    {
        $columns = MySqlStatements::parsedColumns("CREATE TABLE t (a BIT(8) DEFAULT b'101', b VARBINARY(2) DEFAULT 0x4142)");

        self::assertSame(5, $columns['a']->default);
        self::assertSame('AB', $columns['b']->default);
    }
}
