<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\CreateTableStatement as Subject;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
final class CreateTableStatementTest extends TestCase
{
    public function testLocateReturnsTheCommandHoldingTheCreateTable(): void
    {
        $sql = 'SELECT 1; CREATE TABLE t (id INT);';
        $command = (new Subject())->locate((new SqliteParser())->parse($sql), $sql);

        self::assertSame('cmd', $command->name);
        self::assertSame('CREATE TABLE t (id INT)', $command->text($sql));
    }

    public function testLocateRejectsEmptyInput(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\InvalidSqlException::class);
        $this->expectExceptionMessage('No statements found');
        (new Subject())->locate((new SqliteParser())->parse(''), '');
    }

    public function testLocateRejectsOtherStatements(): void
    {
        $this->expectException(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class);
        (new Subject())->locate((new SqliteParser())->parse('CREATE INDEX i ON t (id);'), 'CREATE INDEX i ON t (id);');
    }

    public function testColumnsPairsEveryColumnNameWithItsConstraints(): void
    {
        $sql = 'CREATE TABLE t (a INT NOT NULL, b, c TEXT DEFAULT 1, PRIMARY KEY (a))';
        $command = (new Subject())->locate((new SqliteParser())->parse($sql), $sql);
        $columns = (new Subject())->columns($command);

        self::assertSame(['a INT', 'b', 'c TEXT'], array_map(static fn (array $pair): string => $pair[0]->text($sql), $columns));
        self::assertSame(['NOT NULL', '', 'DEFAULT 1'], array_map(static fn (array $pair): string => $pair[1]->text($sql), $columns));
    }

    public function testColumnsIsEmptyForCreateTableAsSelect(): void
    {
        $sql = 'CREATE TABLE t AS SELECT 1';
        $command = (new Subject())->locate((new SqliteParser())->parse($sql), $sql);

        self::assertSame([], (new Subject())->columns($command));
        self::assertSame([], (new Subject())->columns(new Node('cmd', 0, [])));
    }

    public function testPairsIgnoresTokensAndUnpairedLists(): void
    {
        $sql = 'CREATE TABLE t (a INT)';
        $columnname = (new SqliteParser())->parse($sql)->find('columnname')[0];
        $carglist = (new SqliteParser())->parse($sql)->find('carglist')[0];

        self::assertSame([], (new Subject())->pairs(new Node('columnlist', 0, [$carglist])));
        self::assertCount(1, (new Subject())->pairs(new Node('columnlist', 0, [$columnname, $carglist])));
    }

    public function testConstraintsListsTableConstraintsOnly(): void
    {
        $sql = 'CREATE TABLE t (a INT, b INT, CONSTRAINT pk PRIMARY KEY (a), UNIQUE (b), FOREIGN KEY (b) REFERENCES o (id))';
        $command = (new Subject())->locate((new SqliteParser())->parse($sql), $sql);

        self::assertSame(['CONSTRAINT pk', 'PRIMARY KEY (a)', 'UNIQUE (b)', 'FOREIGN KEY (b) REFERENCES o (id)'], array_map(static fn (Node $node): string => $node->text($sql), (new Subject())->constraints($command)));
        self::assertSame([], (new Subject())->constraints(new Node('cmd', 0, [])));
    }
}
