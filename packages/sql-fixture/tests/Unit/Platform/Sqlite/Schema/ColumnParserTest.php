<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\Sqlite\Schema\ColumnConstraints;
use SqlFixture\Platform\Sqlite\Schema\ColumnParser as Subject;
use SqlFixture\Platform\Sqlite\Schema\DefaultExpression;
use SqlFixture\Platform\Sqlite\Schema\TypeDeclaration;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TypeShape;
use Tests\Statement\SqliteStatements;

#[CoversClass(Subject::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(ColumnConstraints::class)]
#[UsesClass(DefaultExpression::class)]
#[UsesClass(TypeDeclaration::class)]
final class ColumnParserTest extends TestCase
{
    public function testParseReadsTypeConstraintsAndDefault(): void
    {
        $column = SqliteStatements::parsedColumns("CREATE TABLE t (name VARCHAR(30) NOT NULL DEFAULT 'a''b' COLLATE NOCASE)")['name'];

        self::assertSame('VARCHAR', $column->type);
        self::assertSame(30, $column->length);
        self::assertFalse($column->nullable);
        self::assertSame("a'b", $column->default);
        self::assertFalse($column->autoIncrement);
        self::assertFalse($column->generated);
        self::assertFalse($column->unsigned);
        self::assertNull($column->enumValues);
    }

    public function testParseMarksKeyAutoincrementAndGeneratedColumns(): void
    {
        $columns = SqliteStatements::parsedColumns('CREATE TABLE t (id INTEGER PRIMARY KEY AUTOINCREMENT, k TEXT, g INT AS (id + 1), s TEXT GENERATED ALWAYS AS (k) STORED, v)', ['id', 'k']);

        self::assertSame([true, false], [$columns['id']->autoIncrement, $columns['id']->nullable]);
        self::assertFalse($columns['k']->nullable);
        self::assertSame(['INT', true, true], [$columns['g']->type, $columns['g']->generated, $columns['g']->nullable]);
        self::assertSame(['TEXT', true], [$columns['s']->type, $columns['s']->generated]);
        self::assertSame(['BLOB', true], [$columns['v']->type, $columns['v']->nullable]);
    }

    public function testParseTakesNullabilityFromTheAnalysis(): void
    {
        $columns = SqliteStatements::parsedColumns('CREATE TABLE t (a INT NOT NULL NULL, b INT NULL NOT NULL, c INT)');

        self::assertFalse($columns['a']->nullable);
        self::assertFalse($columns['b']->nullable);
        self::assertTrue($columns['c']->nullable);
    }
}
