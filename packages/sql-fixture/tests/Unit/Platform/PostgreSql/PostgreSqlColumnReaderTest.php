<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\PostgreSqlColumnReader;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(PostgreSqlColumnReader::class)]
#[UsesClass(ColumnDefinition::class)]
final class PostgreSqlColumnReaderTest extends TestCase
{
    #[DataProvider('providerSerial')]
    public function testReadExpandsSerialIntoTheIntegerTypeItStandsFor(
        string $declared,
        string $type,
    ): void {
        $column = (new PostgreSqlColumnReader())->read('id ' . $declared, []);

        self::assertNotNull($column);
        self::assertSame($type, $column->type);
        self::assertTrue($column->autoIncrement);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSerial(): iterable
    {
        yield 'SERIAL' => ['SERIAL', 'INTEGER'];
        yield 'BIGSERIAL' => ['BIGSERIAL', 'BIGINT'];
        yield 'SMALLSERIAL' => ['SMALLSERIAL', 'SMALLINT'];
    }

    public function testReadWritesAnArrayTypeUnderANameOfItsOwn(): void
    {
        $column = (new PostgreSqlColumnReader())->read('tags TEXT[]', []);

        self::assertNotNull($column);
        self::assertSame('TEXT_ARRAY', $column->type);
    }

    public function testReadTakesALengthAsALengthAndAPrecisionAsAPrecision(): void
    {
        $varchar = (new PostgreSqlColumnReader())->read('name VARCHAR(9)', []);
        $numeric = (new PostgreSqlColumnReader())->read('amount NUMERIC(10)', []);

        self::assertNotNull($varchar);
        self::assertNotNull($numeric);
        self::assertSame(9, $varchar->length);
        self::assertSame(10, $numeric->precision);
        self::assertNull($numeric->length);
    }

    public function testReadMarksAGeneratedColumnAsFilledByTheServer(): void
    {
        $column = (new PostgreSqlColumnReader())->read('total INT GENERATED ALWAYS AS (a + b) STORED', []);

        self::assertNotNull($column);
        self::assertTrue($column->generated);
    }

    public function testReadMarksAColumnNamedByATableLevelKeyNotNull(): void
    {
        $column = (new PostgreSqlColumnReader())->read('id INTEGER', ['id']);

        self::assertNotNull($column);
        self::assertFalse($column->nullable);
    }

    public function testReadAnswersNothingForTextThatDoesNotBeginWithAName(): void
    {
        self::assertNull((new PostgreSqlColumnReader())->read('(id)', []));
    }

    #[DataProvider('providerMultiWordType')]
    public function testTypeReadsTheTypesPostgresSpellsAsMoreThanOneWord(string $rest, string $type): void
    {
        self::assertSame($type, (new PostgreSqlColumnReader())->type($rest));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMultiWordType(): iterable
    {
        yield 'double precision' => ['DOUBLE PRECISION', 'DOUBLE PRECISION'];
        yield 'timestamp with zone' => ['TIMESTAMP WITH TIME ZONE', 'TIMESTAMP WITH TIME ZONE'];
        yield 'timestamp without zone' => ['TIMESTAMP WITHOUT TIME ZONE', 'TIMESTAMP WITHOUT TIME ZONE'];
        yield 'time with zone' => ['TIME WITH TIME ZONE', 'TIME WITH TIME ZONE'];
        yield 'time without zone' => ['TIME WITHOUT TIME ZONE', 'TIME WITHOUT TIME ZONE'];
        yield 'character varying' => ['CHARACTER VARYING(9)', 'CHARACTER VARYING'];
        yield 'one word' => ['INTEGER NOT NULL', 'INTEGER'];
        yield 'nothing at all' => ['', 'TEXT'];
    }

    #[DataProvider('providerExactNumeric')]
    public function testIsExactNumericTellsAPrecisionFromALength(string $type, bool $expected): void
    {
        self::assertSame($expected, (new PostgreSqlColumnReader())->isExactNumeric($type));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerExactNumeric(): iterable
    {
        yield 'DECIMAL' => ['DECIMAL', true];
        yield 'NUMERIC' => ['NUMERIC', true];
        yield 'DEC' => ['DEC', true];
        yield 'VARCHAR' => ['VARCHAR', false];
    }

    #[DataProvider('providerDefault')]
    public function testDefaultValueReadsTheDefaultAsTheTypeItIsWrittenAs(string $rest, mixed $expected): void
    {
        self::assertSame($expected, (new PostgreSqlColumnReader())->defaultValue($rest));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function providerDefault(): iterable
    {
        yield 'none declared' => ['TEXT NOT NULL', null];
        yield 'a quoted string' => ["TEXT DEFAULT 'due'", 'due'];
        yield 'the word null' => ['TEXT DEFAULT NULL', null];
        yield 'true' => ['BOOLEAN DEFAULT TRUE', true];
        yield 'false' => ['BOOLEAN DEFAULT FALSE', false];
        yield 'an integer' => ['INTEGER DEFAULT 42', 42];
        yield 'a float' => ['NUMERIC DEFAULT 1.5', 1.5];
        yield 'a function call' => ['TIMESTAMP DEFAULT now()', 'now()'];
        yield 'a value carrying a cast' => ["TEXT DEFAULT 'due'::text", "'due'::text"];
        yield 'an expression' => ['TEXT DEFAULT (lower(x))', '(lower(x))'];
    }
}
