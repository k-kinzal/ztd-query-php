<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\PostgreSql\Schema\CatalogColumn;
use SqlFixture\Platform\PostgreSql\Schema\ColumnConstraints;
use SqlFixture\Platform\PostgreSql\Schema\ColumnParser;
use SqlFixture\Platform\PostgreSql\Schema\DefaultExpression;
use SqlFixture\Platform\PostgreSql\Schema\TableDefinition as Subject;
use SqlFixture\Platform\PostgreSql\Schema\TypeDeclaration;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TypeShape;
use Tests\Statement\PostgreSqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(\SqlFixture\Schema\Exception\UnanalyzedColumnException::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(CatalogColumn::class)]
#[UsesClass(ColumnConstraints::class)]
#[UsesClass(ColumnParser::class)]
#[UsesClass(DefaultExpression::class)]
#[UsesClass(TypeDeclaration::class)]
final class TableDefinitionTest extends TestCase
{
    public function testColumnsKeepsDeclarationOrderAndSkipsConstraints(): void
    {
        [$operation, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t (id INT, PRIMARY KEY (id), name VARCHAR(10) NOT NULL, LIKE o, EXCLUDE USING gist (id WITH =))');
        $columns = (new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), ['id']);

        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
        self::assertSame(10, $columns['name']->length);
    }

    public function testColumnsFoldsUnquotedNames(): void
    {
        [$operation, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t (Plain INT, "Quo""ted" INT)');

        self::assertSame(['plain', 'Quo"ted'], array_keys((new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), [])));
    }

    public function testColumnsIsEmptyWithoutAColumnList(): void
    {
        [$operation, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t OF some_type');

        self::assertSame([], (new Subject())->elements($statement));
        self::assertSame([], (new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), []));
    }

    public function testPrimaryKeysReadsColumnAndTableKeysWithoutIncludedColumns(): void
    {
        [$columnOperation, $column] = PostgreSqlStatements::analyzed('CREATE TABLE t (a INT CONSTRAINT pk PRIMARY KEY, b INT UNIQUE, c INT REFERENCES o (id))');
        [$tableOperation, $table] = PostgreSqlStatements::analyzed('CREATE TABLE t (a INT, "B" INT, c INT, CONSTRAINT pk PRIMARY KEY (a, "B") INCLUDE (c), UNIQUE (c))');

        self::assertSame(['a'], (new Subject())->primaryKeys($columnOperation, $column));
        self::assertSame(['a', 'B'], (new Subject())->primaryKeys($tableOperation, $table));
    }

    public function testElementsListsColumnsAndTableConstraintsInOrder(): void
    {
        [, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t (id INT, PRIMARY KEY (id), name TEXT)');

        self::assertCount(3, (new Subject())->elements($statement));
    }

    public function testPrimaryKeysNamesARepeatedKeyColumnOnce(): void
    {
        [$operation, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t (a INT, b INT, PRIMARY KEY (a, b, a))');

        self::assertSame(['a', 'b'], (new Subject())->primaryKeys($operation, $statement));
    }

    public function testColumnsRejectsAColumnTheAnalysisDoesNotDeclare(): void
    {
        [$operation, $statement] = PostgreSqlStatements::analyzed('CREATE TABLE t (id INT, n NUMERIC(10, -2), tail TEXT)');

        $this->expectException(\SqlFixture\Schema\Exception\UnanalyzedColumnException::class);
        $this->expectExceptionMessage('Column could not be analyzed: n');
        (new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), []);
    }
}
