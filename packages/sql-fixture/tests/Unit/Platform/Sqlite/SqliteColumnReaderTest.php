<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\SqliteColumnReader;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(SqliteColumnReader::class)]
#[UsesClass(ColumnDefinition::class)]
final class SqliteColumnReaderTest extends TestCase
{
    public function testReadTakesTheNameAndTheTypeThatFollowsIt(): void
    {
        $column = (new SqliteColumnReader())->read('name TEXT', []);

        self::assertNotNull($column);
        self::assertSame('name', $column->name);
        self::assertSame('TEXT', $column->type);
    }

    public function testReadTakesTheLengthDeclaredInParentheses(): void
    {
        $column = (new SqliteColumnReader())->read('name VARCHAR(9)', []);

        self::assertNotNull($column);
        self::assertSame(9, $column->length);
    }

    public function testReadTakesAPrecisionAndScaleAsTwoNumbers(): void
    {
        $column = (new SqliteColumnReader())->read('amount DECIMAL(10, 2)', []);

        self::assertNotNull($column);
        self::assertSame(10, $column->precision);
        self::assertSame(2, $column->scale);
    }

    public function testReadMarksAColumnDeclaredNotNull(): void
    {
        $column = (new SqliteColumnReader())->read('name TEXT NOT NULL', []);

        self::assertNotNull($column);
        self::assertFalse($column->nullable);
    }

    public function testReadMarksAColumnNamedByATableLevelKeyNotNull(): void
    {
        $column = (new SqliteColumnReader())->read('id INTEGER', ['id']);

        self::assertNotNull($column);
        self::assertFalse($column->nullable);
    }

    public function testReadMarksAnAutoincrementColumnAsFilledByTheServer(): void
    {
        $column = (new SqliteColumnReader())->read('id INTEGER PRIMARY KEY AUTOINCREMENT', []);

        self::assertNotNull($column);
        self::assertTrue($column->autoIncrement);
    }

    public function testReadMarksAGeneratedColumnAsFilledByTheServer(): void
    {
        $column = (new SqliteColumnReader())->read('total INTEGER AS (a + b)', []);

        self::assertNotNull($column);
        self::assertTrue($column->generated);
    }

    public function testReadAnswersNothingForTextThatDoesNotBeginWithAName(): void
    {
        self::assertNull((new SqliteColumnReader())->read('(id)', []));
    }

    public function testTypeAnswersBlobForAColumnDeclaredWithNoType(): void
    {
        self::assertSame('BLOB', (new SqliteColumnReader())->type(''));
    }

    public function testTypeAnswersTheTypeInCapitals(): void
    {
        self::assertSame('TEXT', (new SqliteColumnReader())->type('text not null'));
    }

    #[DataProvider('providerDefault')]
    public function testDefaultValueReadsTheDefaultAsTheTypeItIsWrittenAs(string $rest, mixed $expected): void
    {
        self::assertSame($expected, (new SqliteColumnReader())->defaultValue($rest));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function providerDefault(): iterable
    {
        yield 'none declared' => ['TEXT NOT NULL', null];
        yield 'a quoted string' => ["TEXT DEFAULT 'due'", 'due'];
        yield 'the word null' => ['TEXT DEFAULT NULL', null];
        yield 'one' => ['INTEGER DEFAULT 1', true];
        yield 'zero' => ['INTEGER DEFAULT 0', false];
        yield 'true' => ['INTEGER DEFAULT TRUE', true];
        yield 'false' => ['INTEGER DEFAULT FALSE', false];
        yield 'an integer' => ['INTEGER DEFAULT 42', 42];
        yield 'a float' => ['REAL DEFAULT 1.5', 1.5];
        yield 'an expression' => ['TEXT DEFAULT (lower(x))', '(lower(x))'];
        yield 'a function the server evaluates' => ['TEXT DEFAULT CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'];
        yield 'followed by another clause' => ["TEXT DEFAULT 'due' NOT NULL", 'due'];
    }
}
