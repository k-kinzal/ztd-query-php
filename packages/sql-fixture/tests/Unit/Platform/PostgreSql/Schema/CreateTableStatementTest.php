<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CreateTableStatement as Subject;
use SqlParser\Parser\Node;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
final class CreateTableStatementTest extends TestCase
{
    public function testLocateReadsTheOneCreateTableBesideOtherStatements(): void
    {
        $sql = 'DROP TABLE IF EXISTS t; CREATE TABLE t (id INT);';
        $statement = (new Subject())->locate((new PostgreSqlParser())->parse($sql), $sql);

        self::assertSame('CreateStmt', $statement->name);
        self::assertSame('CREATE TABLE t (id INT)', $statement->text($sql));
    }

    public function testLocateRejectsTextDeclaringMoreThanOneTable(): void
    {
        $sql = 'CREATE TABLE t (id INT); CREATE TABLE u (id INT);';

        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('More than one CREATE TABLE statement');
        (new Subject())->locate((new PostgreSqlParser())->parse($sql), $sql);
    }

    public function testLocateRejectsEmptyInput(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('No statements found');
        (new Subject())->locate((new PostgreSqlParser())->parse(''), '');
    }

    public function testLocateRejectsOtherStatements(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class);
        (new Subject())->locate((new PostgreSqlParser())->parse('CREATE TABLE t AS SELECT 1'), 'CREATE TABLE t AS SELECT 1');
    }

    public function testElementsListsColumnsAndConstraintsInOrder(): void
    {
        $sql = 'CREATE TABLE t (id INT, PRIMARY KEY (id), name TEXT, LIKE o, CONSTRAINT u UNIQUE (name))';
        $statement = (new Subject())->locate((new PostgreSqlParser())->parse($sql), $sql);

        self::assertSame(['id INT', 'PRIMARY KEY (id)', 'name TEXT', 'LIKE o', 'CONSTRAINT u UNIQUE (name)'], array_map(static fn (Node $element): string => $element->text($sql), (new Subject())->elements($statement)));
        self::assertSame([], (new Subject())->elements(new Node('CreateStmt', 0, [])));
    }
}
