<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlBinarySample;
use SqlFixture\Platform\MySql\MySqlColumnSample;
use SqlFixture\Platform\MySql\MySqlEnumerationSample;
use SqlFixture\Platform\MySql\MySqlNumberSample;
use SqlFixture\Platform\MySql\MySqlTextSample;
use SqlFixture\Platform\MySql\WellKnownTextGeometry;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\SpyGenerator;

#[CoversClass(MySqlColumnSample::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(MySqlBinarySample::class)]
#[UsesClass(MySqlEnumerationSample::class)]
#[UsesClass(MySqlNumberSample::class)]
#[UsesClass(MySqlTextSample::class)]
#[UsesClass(WellKnownTextGeometry::class)]
final class MySqlColumnSampleTest extends TestCase
{
    #[DataProvider('providerTypeAndPhpType')]
    public function testOfAnswersAValueOfTheKindTheTypeCallsFor(string $type, string $phpType): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('c', $type, length: 4));

        self::assertSame($phpType, get_debug_type($value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTypeAndPhpType(): iterable
    {
        yield 'SMALLINT' => ['SMALLINT', 'int'];
        yield 'MEDIUMINT' => ['MEDIUMINT', 'int'];
        yield 'INT' => ['INT', 'int'];
        yield 'INTEGER' => ['INTEGER', 'int'];
        yield 'BIGINT' => ['BIGINT', 'int'];
        yield 'YEAR' => ['YEAR', 'int'];
        yield 'BIT' => ['BIT', 'int'];
        yield 'FLOAT' => ['FLOAT', 'float'];
        yield 'DOUBLE' => ['DOUBLE', 'float'];
        yield 'REAL' => ['REAL', 'float'];
        yield 'DECIMAL' => ['DECIMAL', 'float'];
        yield 'NUMERIC' => ['NUMERIC', 'float'];
        yield 'DEC' => ['DEC', 'float'];
        yield 'FIXED' => ['FIXED', 'float'];
        yield 'CHAR' => ['CHAR', 'string'];
        yield 'VARCHAR' => ['VARCHAR', 'string'];
        yield 'TINYTEXT' => ['TINYTEXT', 'string'];
        yield 'TEXT' => ['TEXT', 'string'];
        yield 'MEDIUMTEXT' => ['MEDIUMTEXT', 'string'];
        yield 'LONGTEXT' => ['LONGTEXT', 'string'];
        yield 'BINARY' => ['BINARY', 'string'];
        yield 'VARBINARY' => ['VARBINARY', 'string'];
        yield 'TINYBLOB' => ['TINYBLOB', 'string'];
        yield 'BLOB' => ['BLOB', 'string'];
        yield 'MEDIUMBLOB' => ['MEDIUMBLOB', 'string'];
        yield 'LONGBLOB' => ['LONGBLOB', 'string'];
        yield 'DATE' => ['DATE', 'string'];
        yield 'TIME' => ['TIME', 'string'];
        yield 'DATETIME' => ['DATETIME', 'string'];
        yield 'TIMESTAMP' => ['TIMESTAMP', 'string'];
        yield 'JSON' => ['JSON', 'string'];
        yield 'BOOL' => ['BOOL', 'bool'];
        yield 'BOOLEAN' => ['BOOLEAN', 'bool'];
        yield 'a type nothing names' => ['SOMETHING_ELSE', 'string'];
    }

    #[DataProvider('providerGeometryType')]
    public function testOfWritesEverySpatialTypeUnderItsOwnKeyword(string $type, string $keyword): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('g', $type));

        self::assertIsString($value);
        self::assertStringStartsWith($keyword . '(', $value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGeometryType(): iterable
    {
        yield 'POINT' => ['POINT', 'POINT'];
        yield 'GEOMETRY' => ['GEOMETRY', 'POINT'];
        yield 'LINESTRING' => ['LINESTRING', 'LINESTRING'];
        yield 'POLYGON' => ['POLYGON', 'POLYGON'];
        yield 'MULTIPOINT' => ['MULTIPOINT', 'MULTIPOINT'];
        yield 'MULTILINESTRING' => ['MULTILINESTRING', 'MULTILINESTRING'];
        yield 'MULTIPOLYGON' => ['MULTIPOLYGON', 'MULTIPOLYGON'];
        yield 'GEOMETRYCOLLECTION' => ['GEOMETRYCOLLECTION', 'GEOMETRYCOLLECTION'];
    }

    public function testOfReadsTheTypeWithoutRegardToHowItIsCased(): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('n', 'int'));

        self::assertIsInt($value);
    }

    public function testOfAnswersOneOfTheMembersAnEnumDeclares(): void
    {
        $column = new ColumnDefinition('s', 'ENUM', enumValues: ['paid', 'due']);

        self::assertContains((new MySqlColumnSample())->of(Factory::create(), $column), ['paid', 'due']);
    }

    public function testOfAnswersMembersOfASetSeparatedByCommas(): void
    {
        $column = new ColumnDefinition('s', 'SET', enumValues: ['a']);

        self::assertSame('a', (new MySqlColumnSample())->of(Factory::create(), $column));
    }

    public function testParagraphsSeparatesEachParagraphWithABlankLine(): void
    {
        $text = (new MySqlColumnSample())->paragraphs(Factory::create(), 3);

        self::assertCount(3, explode("\n\n", $text));
    }

    public function testParagraphsDrawsAsManyAsItWasAskedFor(): void
    {
        $text = (new MySqlColumnSample())->paragraphs(Factory::create(), 1);

        self::assertStringNotContainsString("\n\n", $text);
        self::assertNotSame('', $text);
    }

    public function testJsonWritesAnObjectTheServerWillParse(): void
    {
        $written = (new MySqlColumnSample())->json(Factory::create());

        $decoded = json_decode($written, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('key', $decoded);
        self::assertArrayHasKey('value', $decoded);
    }
    #[DataProvider('providerTypeAndSpelling')]
    public function testOfWritesTheTypeInTheSpellingMysqlReadsItFrom(string $type, string $pattern): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('c', $type, length: 6));

        self::assertMatchesRegularExpression($pattern, (string) $value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerTypeAndSpelling(): iterable
    {
        yield 'CHAR' => ['CHAR', '/^.{6}$/'];
        yield 'VARCHAR' => ['VARCHAR', '/^.{1,6}$/s'];
        yield 'TINYTEXT' => ['TINYTEXT', '/^[^\n]+$/'];
        yield 'DATE' => ['DATE', '/^\d{4}-\d{2}-\d{2}$/'];
        yield 'TIME' => ['TIME', '/^\d{2}:\d{2}:\d{2}$/'];
        yield 'DATETIME' => ['DATETIME', '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'];
        yield 'TIMESTAMP' => ['TIMESTAMP', '/^(19[7-9]\d|20[0-3]\d)-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/'];
        yield 'JSON' => ['JSON', '/^\{"key":.*,"value":\d+\}$/'];
    }

    #[DataProvider('providerTextTypeAndParagraphCount')]
    public function testOfWritesAsManyParagraphsAsTheTypeHolds(string $type, int $paragraphs): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('t', $type));

        self::assertCount($paragraphs, explode("\n\n", (string) $value));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerTextTypeAndParagraphCount(): iterable
    {
        yield 'TEXT' => ['TEXT', 2];
        yield 'MEDIUMTEXT' => ['MEDIUMTEXT', 3];
        yield 'LONGTEXT' => ['LONGTEXT', 5];
    }

    public function testOfDrawsTwoHundredAndFiftyFiveCharactersForATinyText(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('t', 'TINYTEXT'));

        self::assertSame([[255]], $faker->methodCalls['text'] ?? []);
    }

    public function testOfFallsBackToFiftyCharactersOfTextForATypeNothingNames(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('x', 'SOMETHING_ELSE'));

        self::assertSame([[50]], $faker->methodCalls['text'] ?? []);
    }

    public function testOfKeepsAFloatWithinAThousandEitherWay(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('f', 'FLOAT'));

        self::assertSame([[2, -1000.0, 1000.0]], $faker->randomFloatCalls);
    }

    #[DataProvider('providerWideFloatType')]
    public function testOfGivesAWiderFloatTheRangeItsTypeSuggests(string $type): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('f', $type));

        self::assertSame([[4, -1000000.0, 1000000.0]], $faker->randomFloatCalls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerWideFloatType(): iterable
    {
        yield 'DOUBLE' => ['DOUBLE'];
        yield 'REAL' => ['REAL'];
    }

    public function testOfDrawsAYearFromTheYearsMysqlHolds(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('y', 'YEAR'));

        self::assertSame([[1901, 2155]], $faker->numberBetweenCalls);
    }

    public function testOfFillsATinyBlobToTheLengthItsTypeAllows(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('b', 'TINYBLOB'));

        self::assertSame([[1, 255]], $faker->numberBetweenCalls);
    }

    #[DataProvider('providerLargeBlobType')]
    public function testOfFillsALargerBlobToTheLengthItsTypeAllows(string $type): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('b', $type));

        self::assertSame([[1, 1000]], $faker->numberBetweenCalls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLargeBlobType(): iterable
    {
        yield 'BLOB' => ['BLOB'];
        yield 'MEDIUMBLOB' => ['MEDIUMBLOB'];
        yield 'LONGBLOB' => ['LONGBLOB'];
    }

    #[DataProvider('providerIntegerTypeAndRange')]
    public function testOfDrawsEachWholeNumberTypeFromTheRangeMysqlDeclaresForIt(string $type, int $low, int $high): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('n', $type, length: 6));

        self::assertSame([[$low, $high]], $faker->numberBetweenCalls);
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function providerIntegerTypeAndRange(): iterable
    {
        yield 'TINYINT' => ['TINYINT', -128, 127];
        yield 'SMALLINT' => ['SMALLINT', -32768, 32767];
        yield 'MEDIUMINT' => ['MEDIUMINT', -8388608, 8388607];
        yield 'INT' => ['INT', -2147483648, 2147483647];
        yield 'INTEGER' => ['INTEGER', -2147483648, 2147483647];
        yield 'BIGINT' => ['BIGINT', PHP_INT_MIN, PHP_INT_MAX];
    }

    #[DataProvider('providerExactNumericType')]
    public function testOfDrawsAnExactNumericToTheDigitsItDeclares(string $type): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('d', $type, precision: 6, scale: 3));

        self::assertSame([[3, -999.0, 999.0]], $faker->randomFloatCalls);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerExactNumericType(): iterable
    {
        yield 'DECIMAL' => ['DECIMAL'];
        yield 'NUMERIC' => ['NUMERIC'];
        yield 'DEC' => ['DEC'];
        yield 'FIXED' => ['FIXED'];
    }

    public function testOfDrawsABitFromZeroToWhatTheDeclaredBitsHold(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('b', 'BIT', length: 4));

        self::assertSame([[0, 15]], $faker->numberBetweenCalls);
    }

    public function testOfFillsABinaryColumnToExactlyItsDeclaredLength(): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('b', 'BINARY', length: 9));

        self::assertSame(9, strlen((string) $value));
    }

    public function testOfDrawsAVarbinaryUpToItsDeclaredLength(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->of($faker, new ColumnDefinition('b', 'VARBINARY', length: 32));

        self::assertSame([[1, 32]], $faker->numberBetweenCalls);
    }

    #[DataProvider('providerBooleanType')]
    public function testOfWritesABooleanAsTrueOrFalseAndNothingElse(string $type): void
    {
        $value = (new MySqlColumnSample())->of(Factory::create(), new ColumnDefinition('b', $type));

        self::assertSame('bool', get_debug_type($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerBooleanType(): iterable
    {
        yield 'BOOL' => ['BOOL'];
        yield 'BOOLEAN' => ['BOOLEAN'];
    }

    public function testJsonDrawsTwentyCharactersOfTextAndANumberUpToAHundred(): void
    {
        $faker = SpyGenerator::create();

        (new MySqlColumnSample())->json($faker);

        self::assertSame([[[20]], [[1, 100]]], [$faker->methodCalls['text'] ?? [], $faker->numberBetweenCalls]);
    }

    public function testJsonCarriesAKeyAndAValue(): void
    {
        $decoded = json_decode((new MySqlColumnSample())->json(Factory::create()), true);

        self::assertSame(['key', 'value'], is_array($decoded) ? array_keys($decoded) : []);
    }
}
