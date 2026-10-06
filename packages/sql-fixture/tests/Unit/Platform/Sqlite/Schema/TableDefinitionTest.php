<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Analysis\NumericLiteral;
use SqlFixture\Platform\Sqlite\Schema\ColumnConstraints;
use SqlFixture\Platform\Sqlite\Schema\ColumnParser;
use SqlFixture\Platform\Sqlite\Schema\DefaultExpression;
use SqlFixture\Platform\Sqlite\Schema\TableDefinition as Subject;
use SqlFixture\Platform\Sqlite\Schema\TypeDeclaration;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use Tests\Statement\SqliteStatements;

#[CoversClass(Subject::class)]
#[UsesClass(\SqlFixture\Schema\Exception\UnanalyzedColumnException::class)]
#[UsesClass(CreateTableOperation::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(NumericLiteral::class)]
#[UsesClass(ColumnConstraints::class)]
#[UsesClass(ColumnParser::class)]
#[UsesClass(DefaultExpression::class)]
#[UsesClass(TypeDeclaration::class)]
final class TableDefinitionTest extends TestCase
{
    public function testColumnsKeepsDeclarationOrderAndMarksKeyColumns(): void
    {
        [$operation, $statement] = SqliteStatements::analyzed('CREATE TABLE t (id INT, name VARCHAR(10), PRIMARY KEY (id), UNIQUE (name))');
        $columns = (new Subject())->columns($statement, (new CreateTableOperation())->columns($operation), ['id']);

        self::assertSame(['id', 'name'], array_keys($columns));
        self::assertFalse($columns['id']->nullable);
        self::assertTrue($columns['name']->nullable);
        self::assertSame(10, $columns['name']->length);
    }

    public function testColumnsRejectsAColumnTheAnalysisDoesNotDeclare(): void
    {
        [, $statement] = SqliteStatements::analyzed('CREATE TABLE t (id INT, name TEXT)');
        [$other] = SqliteStatements::analyzed('CREATE TABLE t (id INT)');


        $this->expectException(\SqlFixture\Schema\Exception\UnanalyzedColumnException::class);
        $this->expectExceptionMessage('Column could not be analyzed: name');
        (new Subject())->columns($statement, (new CreateTableOperation())->columns($other), []);
    }

    public function testPrimaryKeysResolvesEveryKeyTermToItsColumn(): void
    {
        [$columnKey, $columnStatement] = SqliteStatements::analyzed('CREATE TABLE t (a INT PRIMARY KEY, b INT UNIQUE)');
        [$tableKey, $tableStatement] = SqliteStatements::analyzed("CREATE TABLE t (a INT, \"b\" INT, c INT, d INT, CONSTRAINT pk PRIMARY KEY ('a', \"b\" DESC, c COLLATE nocase, ((d))), UNIQUE (c))");

        self::assertSame(['a'], (new Subject())->primaryKeys($columnKey, $columnStatement));
        self::assertSame(['a', 'b', 'c', 'd'], (new Subject())->primaryKeys($tableKey, $tableStatement));
    }

    public function testPrimaryKeysMatchesColumnNamesWithoutRegardToCase(): void
    {
        [$operation, $statement] = SqliteStatements::analyzed('CREATE TABLE t (Id INT, PRIMARY KEY (ID))');

        self::assertSame(['Id'], (new Subject())->primaryKeys($operation, $statement));
    }

    public function testOperandRemovesParenthesesAndCollations(): void
    {
        [, $statement] = SqliteStatements::analyzed('CREATE TABLE t (a INT, PRIMARY KEY ((a) COLLATE nocase))');
        $key = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TablePrimaryKey::class, $key);

        self::assertInstanceOf(ColumnUse::class, (new Subject())->operand($key->terms[0]->expression));
    }

    public function testPrimaryKeysNamesARepeatedKeyColumnOnce(): void
    {
        [$operation, $statement] = SqliteStatements::analyzed('CREATE TABLE t (a INT, b INT, PRIMARY KEY (a, b, a))');

        self::assertSame(['a', 'b'], (new Subject())->primaryKeys($operation, $statement));
    }
}
