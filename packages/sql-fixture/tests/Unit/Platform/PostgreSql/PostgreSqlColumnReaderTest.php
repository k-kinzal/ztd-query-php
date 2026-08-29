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
    public function testReadTakesTheNameAndTypeHoweverTheyAreCased(): void
    {
        $column = (new PostgreSqlColumnReader())->read('"amount" numeric(8,2) not null', []);

        self::assertSame(
            ['amount', 'NUMERIC', 8, 2, false],
            [$column?->name, $column?->type, $column?->precision, $column?->scale, $column?->nullable],
        );
    }

    public function testReadTakesAColumnWhoseDeclarationRunsOverSeveralLines(): void
    {
        $column = (new PostgreSqlColumnReader())->read("id\n  INTEGER\n  PRIMARY KEY", []);

        self::assertSame(['id', 'INTEGER', false], [$column?->name, $column?->type, $column?->nullable]);
    }

    public function testReadIgnoresTheSpaceAroundWhatFollowsTheName(): void
    {
        $column = (new PostgreSqlColumnReader())->read('id    INTEGER  ', []);

        self::assertSame('INTEGER', $column?->type);
    }

    public function testReadKeepsTheTypeASerialStandsForRatherThanTheOneInBrackets(): void
    {
        $column = (new PostgreSqlColumnReader())->read('id bigserial(10)', []);

        self::assertSame(['BIGINT', true], [$column?->type, $column?->autoIncrement]);
    }

    public function testReadReadsAPrecisionDeclaredWithoutAScaleAsNoFraction(): void
    {
        $column = (new PostgreSqlColumnReader())->read('amount NUMERIC(9)', []);

        self::assertSame([9, 0], [$column?->precision, $column?->scale]);
    }

    public function testReadReadsALengthDeclaredOnATypeThatIsNotExactAsALength(): void
    {
        $column = (new PostgreSqlColumnReader())->read('name VARCHAR(30)', []);

        self::assertSame([30, null, null], [$column?->length, $column?->precision, $column?->scale]);
    }

    public function testReadCallsAColumnNamedInTheTablesKeyANotNullOne(): void
    {
        $column = (new PostgreSqlColumnReader())->read('id INTEGER', ['id']);

        self::assertFalse($column?->nullable);
    }

    public function testReadCallsAColumnOutsideTheTablesKeyANullableOne(): void
    {
        $column = (new PostgreSqlColumnReader())->read('name TEXT', ['id']);

        self::assertTrue($column?->nullable);
    }

    public function testReadCallsAColumnDeclaredNotNullANotNullOne(): void
    {
        $column = (new PostgreSqlColumnReader())->read('name TEXT NOT NULL', []);

        self::assertFalse($column?->nullable);
    }

    public function testReadSaysPostgresHasNoUnsignedColumn(): void
    {
        $column = (new PostgreSqlColumnReader())->read('n INTEGER', []);

        self::assertFalse($column?->unsigned);
    }

    public function testReadReadsAGeneratedColumnHoweverItIsCased(): void
    {
        $column = (new PostgreSqlColumnReader())->read('total INTEGER generated always as (a + b) stored', []);

        self::assertTrue($column?->generated);
    }

    public function testTypeAnswersTextForADeclarationThatNamesNoType(): void
    {
        self::assertSame('TEXT', (new PostgreSqlColumnReader())->type(''));
    }

    public function testTypeAnswersTheNameInUpperCaseHoweverItWasWritten(): void
    {
        self::assertSame('VARCHAR', (new PostgreSqlColumnReader())->type('varchar(30) NOT NULL'));
    }

    public function testTypeKeepsTheBracketsThatSayAColumnHoldsAnArray(): void
    {
        self::assertSame('INTEGER[]', (new PostgreSqlColumnReader())->type('integer[] NOT NULL'));
    }

    public function testDefaultValueKeepsAnExpressionInItsBracketsAsItStands(): void
    {
        self::assertSame('(a + b)', (new PostgreSqlColumnReader())->defaultValue('DEFAULT (a + b)'));
    }

    public function testDefaultValueKeepsACallAsItStandsHoweverItIsCased(): void
    {
        self::assertSame('now()', (new PostgreSqlColumnReader())->defaultValue('DEFAULT now()'));
    }

    public function testDefaultValueKeepsACastAsItStands(): void
    {
        self::assertSame("'active'::text", (new PostgreSqlColumnReader())->defaultValue("DEFAULT 'active'::text"));
    }

    public function testDefaultValueIgnoresTheSpaceAroundWhatWasWritten(): void
    {
        self::assertSame('ready', (new PostgreSqlColumnReader())->defaultValue("DEFAULT    'ready'   "));
    }

    #[DataProvider('providerWrittenDefaultAndValue')]
    public function testDefaultValueReadsWhatWasWrittenAsTheValueItStandsFor(string $written, int|float|string|bool|null $value): void
    {
        self::assertSame($value, (new PostgreSqlColumnReader())->defaultValue($written));
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
        yield 'a whole number' => ['DEFAULT 42', 42];
        yield 'a fractional number' => ['DEFAULT 1.5', 1.5];
        yield 'a bare word' => ['DEFAULT CURRENT_DATE', 'CURRENT_DATE'];
        yield 'nothing written at all' => ['NOT NULL', null];
    }

    public function testDefaultValueStopsAtTheConstraintThatFollowsIt(): void
    {
        self::assertSame(7, (new PostgreSqlColumnReader())->defaultValue('DEFAULT 7 NOT NULL'));
    }

    public function testDeclaredSizeReadsALengthWrittenInBrackets(): void
    {
        $size = (new PostgreSqlColumnReader())->declaredSize('VARCHAR(30) NOT NULL', 'VARCHAR', false);

        self::assertSame(['type' => 'VARCHAR', 'length' => 30, 'precision' => null, 'scale' => null], $size);
    }

    public function testDeclaredSizeReadsAPrecisionAndScaleWrittenTogether(): void
    {
        $size = (new PostgreSqlColumnReader())->declaredSize('NUMERIC(10, 2)', 'NUMERIC', false);

        self::assertSame(['type' => 'NUMERIC', 'length' => null, 'precision' => 10, 'scale' => 2], $size);
    }

    public function testDeclaredSizeCountsDigitsWhenAnExactNumericNamesOneNumber(): void
    {
        $size = (new PostgreSqlColumnReader())->declaredSize('DECIMAL(8)', 'DECIMAL', false);

        self::assertSame(['type' => 'DECIMAL', 'length' => null, 'precision' => 8, 'scale' => 0], $size);
    }

    public function testDeclaredSizeKeepsTheTypeASerialNameStoodFor(): void
    {
        $size = (new PostgreSqlColumnReader())->declaredSize('NUMERIC(10, 2)', 'INTEGER', true);

        self::assertSame('INTEGER', $size['type']);
    }

    public function testDeclaredSizeAnswersNoSizeWhenTheDeclarationWritesNone(): void
    {
        $size = (new PostgreSqlColumnReader())->declaredSize('TEXT NOT NULL', 'TEXT', false);

        self::assertSame(['type' => 'TEXT', 'length' => null, 'precision' => null, 'scale' => null], $size);
    }
}
