<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\CreateTableStatement as Subject;
use SqlParser\MySql\MySqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
final class CreateTableStatementTest extends TestCase
{
    public function testLocateReturnsTheCreateTableNode(): void
    {
        $sql = 'CREATE TABLE t (id INT) ENGINE=InnoDB';
        $statement = (new Subject())->locate((new MySqlParser())->parse($sql), $sql);

        self::assertSame('create_table_stmt', $statement->name);
        self::assertSame($sql, $statement->text($sql));
    }

    public function testLocateRejectsEmptyInput(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('No statements found');
        (new Subject())->locate((new MySqlParser())->parse('  '), '  ');
    }

    public function testLocateRejectsOtherStatements(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class);
        (new Subject())->locate((new MySqlParser())->parse('CREATE INDEX i ON t (id)'), 'CREATE INDEX i ON t (id)');
    }

    public function testElementsListsColumnsAndConstraintsInOrder(): void
    {
        $sql = 'CREATE TABLE t (id INT, PRIMARY KEY (id), name TEXT, KEY k (id))';
        $statement = (new Subject())->locate((new MySqlParser())->parse($sql), $sql);
        $elements = (new Subject())->elements($statement);

        self::assertSame(['id INT', 'PRIMARY KEY (id)', 'name TEXT', 'KEY k (id)'], array_map(static fn ($element): string => $element->text($sql), $elements));
    }

    public function testElementsIsEmptyWithoutAColumnList(): void
    {
        $sql = 'CREATE TABLE t LIKE o';
        $statement = (new Subject())->locate((new MySqlParser())->parse($sql), $sql);

        self::assertSame([], (new Subject())->elements($statement));
    }
}
