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
}
