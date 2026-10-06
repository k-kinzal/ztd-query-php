<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\MySql\Schema\ColumnAttributes;
use SqlFixture\Platform\MySql\Schema\ColumnParser;
use SqlFixture\Platform\MySql\Schema\DefaultExpression;
use SqlFixture\Platform\MySql\Schema\TableDefinition as Subject;
use SqlFixture\Platform\MySql\Schema\TypeParameters;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TypeShape;
use Tests\Statement\MySqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(\SqlFixture\Schema\Exception\UnanalyzedColumnException::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(ColumnAttributes::class)]
#[UsesClass(ColumnParser::class)]
#[UsesClass(DefaultExpression::class)]
#[UsesClass(TypeParameters::class)]
final class TableDefinitionTest extends TestCase
{
    public function testColumnsKeepsDeclarationOrderAndSkipsConstraints(): void
    {
        [$operation, $statement] = MySqlStatements::analyzed('CREATE TABLE t (id INT, PRIMARY KEY (id), name VARCHAR(10) NOT NULL, KEY k (name))');
        $columns = (new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), ['id']);

        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
        self::assertFalse($columns['name']->nullable);
        self::assertSame(10, $columns['name']->length);
    }

    public function testColumnsRejectsAColumnTheAnalysisDoesNotDeclare(): void
    {
        [, $statement] = MySqlStatements::analyzed('CREATE TABLE t (id INT, name TEXT)');
        [$other] = MySqlStatements::analyzed('CREATE TABLE t (id INT)');


        $this->expectException(\SqlFixture\Schema\Exception\UnanalyzedColumnException::class);
        $this->expectExceptionMessage('Column could not be analyzed: name');
        (new Subject())->columns($statement, (new CreateTableOperation())->columns($other), []);
    }

    public function testColumnsIsEmptyWithoutAColumnList(): void
    {
        [$operation, $statement] = MySqlStatements::analyzed('CREATE TABLE t AS SELECT 1 AS a');

        self::assertSame([], (new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), []));
    }

    public function testPrimaryKeysCombinesColumnAndTableLevelKeysWithoutDuplicates(): void
    {
        [$operation, $statement] = MySqlStatements::analyzed('CREATE TABLE t (a INT, b INT KEY, `c` INT, d INT, UNIQUE KEY u (d), FOREIGN KEY (b) REFERENCES o (id))');
        [$tableOperation, $table] = MySqlStatements::analyzed('CREATE TABLE t (a INT, `c` INT, d INT, PRIMARY KEY (a, `c`(10), d DESC), UNIQUE KEY u (d))');

        self::assertSame(['b'], (new Subject())->primaryKeys($operation, $statement));
        self::assertSame(['a', 'c', 'd'], (new Subject())->primaryKeys($tableOperation, $table));
    }

    public function testPrimaryKeysSkipsExpressionKeyParts(): void
    {
        [$operation, $statement] = MySqlStatements::analyzed('CREATE TABLE t (a INT, b INT, PRIMARY KEY ((a + b), b))');
        [$noneOperation, $none] = MySqlStatements::analyzed('CREATE TABLE t (a INT)');

        self::assertSame(['b'], (new Subject())->primaryKeys($operation, $statement));
        self::assertSame([], (new Subject())->primaryKeys($noneOperation, $none));
    }

    public function testPrimaryKeysNamesAKeyColumnAsItIsDeclared(): void
    {
        [$operation, $statement] = MySqlStatements::analyzed('CREATE TABLE t (id INT, Name TEXT, PRIMARY KEY (ID, name(3)))');

        self::assertSame(['id', 'Name'], (new Subject())->primaryKeys($operation, $statement));
    }

    public function testPrimaryKeysNamesARepeatedKeyColumnOnce(): void
    {
        [$operation, $statement] = MySqlStatements::analyzed('CREATE TABLE t (a INT, b INT, PRIMARY KEY (a, b, a))');

        self::assertSame(['a', 'b'], (new Subject())->primaryKeys($operation, $statement));
    }
}
