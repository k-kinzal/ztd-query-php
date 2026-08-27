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
    public function testReadTakesTheNameAndTypeHoweverTheyAreCased(): void
    {
        $column = (new SqliteColumnReader())->read('"amount" decimal(8,2) not null', []);

        self::assertSame(
            ['amount', 'DECIMAL', 8, 2, false],
            [$column?->name, $column?->type, $column?->precision, $column?->scale, $column?->nullable],
        );
    }

    public function testReadTakesAColumnWhoseDeclarationRunsOverSeveralLines(): void
    {
        $column = (new SqliteColumnReader())->read("id\n  INTEGER\n  PRIMARY KEY", []);

        self::assertSame(['id', 'INTEGER', false], [$column?->name, $column?->type, $column?->nullable]);
    }

    public function testReadIgnoresTheSpaceAroundWhatFollowsTheName(): void
    {
        $column = (new SqliteColumnReader())->read('id    INTEGER  ', []);

        self::assertSame('INTEGER', $column?->type);
    }

    public function testReadCallsAColumnNamedInTheTablesKeyANotNullOne(): void
    {
        $column = (new SqliteColumnReader())->read('id INTEGER', ['id']);

        self::assertFalse($column?->nullable);
    }

    public function testReadCallsAColumnOutsideTheTablesKeyANullableOne(): void
    {
        $column = (new SqliteColumnReader())->read('name TEXT', ['id']);

        self::assertTrue($column?->nullable);
    }

    public function testReadCallsAColumnDeclaredNotNullANotNullOne(): void
    {
        $column = (new SqliteColumnReader())->read('name TEXT NOT NULL', []);

        self::assertFalse($column?->nullable);
    }

    public function testReadSaysSqliteHasNoUnsignedColumn(): void
    {
        $column = (new SqliteColumnReader())->read('n INTEGER', []);

        self::assertFalse($column?->unsigned);
    }

    public function testReadReadsAGeneratedColumnHoweverItIsCased(): void
    {
        $column = (new SqliteColumnReader())->read('total INTEGER as (a + b)', []);

        self::assertTrue($column?->generated);
    }

    public function testTypeAnswersBlobForADeclarationThatNamesNoType(): void
    {
        self::assertSame('BLOB', (new SqliteColumnReader())->type(''));
    }

    public function testTypeAnswersTheNameInUpperCaseHoweverItWasWritten(): void
    {
        self::assertSame('VARCHAR', (new SqliteColumnReader())->type('varchar(30) NOT NULL'));
    }

    public function testDefaultValueKeepsAnExpressionInItsBracketsAsItStands(): void
    {
        self::assertSame("(datetime('now'))", (new SqliteColumnReader())->defaultValue("DEFAULT (datetime('now'))"));
    }

    public function testDefaultValueIgnoresTheSpaceAroundWhatWasWritten(): void
    {
        self::assertSame('ready', (new SqliteColumnReader())->defaultValue("DEFAULT    'ready'   "));
    }

    #[DataProvider('providerWrittenDefaultAndValue')]
    public function testDefaultValueReadsWhatWasWrittenAsTheValueItStandsFor(string $written, int|float|string|bool|null $value): void
    {
        self::assertSame($value, (new SqliteColumnReader())->defaultValue($written));
    }

    /**
     * @return iterable<string, array{string, int|float|string|bool|null}>
     */
    public static function providerWrittenDefaultAndValue(): iterable
    {
        yield 'a quoted word' => ["DEFAULT 'ready'", 'ready'];
        yield 'null written in lower case' => ['DEFAULT null', null];
        yield 'true written in lower case' => ['DEFAULT true', true];
        yield 'false written in lower case' => ['DEFAULT false', false];
        yield 'one, which SQLite stores true as' => ['DEFAULT 1', true];
        yield 'zero, which SQLite stores false as' => ['DEFAULT 0', false];
        yield 'a whole number' => ['DEFAULT 42', 42];
        yield 'a fractional number' => ['DEFAULT 1.5', 1.5];
        yield 'a bare word' => ['DEFAULT CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'];
        yield 'nothing written at all' => ['NOT NULL', null];
    }

    public function testDefaultValueStopsAtTheConstraintThatFollowsIt(): void
    {
        self::assertSame(7, (new SqliteColumnReader())->defaultValue('DEFAULT 7 NOT NULL'));
    }
}
