<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\TypeParameters as Subject;
use SqlParser\MySql\MySqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\NodeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\StringLiteral::class)]
final class TypeParametersTest extends TestCase
{
    public function testIsDecimalTypeRecognizesAliases(): void
    {
        self::assertTrue((new Subject())->isDecimalType('FIXED'));
        self::assertTrue((new Subject())->isDecimalType('DEC'));
        self::assertTrue((new Subject())->isDecimalType('NUMERIC'));
        self::assertFalse((new Subject())->isDecimalType('INT'));
    }

    #[DataProvider('providerTypeNames')]
    public function testTypeNameFoldsGrammarSynonyms(string $declaration, string $expected): void
    {
        $tree = (new MySqlParser())->parse("CREATE TABLE t (c {$declaration})");

        self::assertSame($expected, (new Subject())->typeName($tree->find('type')[0]));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTypeNames(): array
    {
        return [
            ['int(11) unsigned', 'INT'],
            ['INTEGER', 'INTEGER'],
            ['DOUBLE PRECISION', 'DOUBLE'],
            ['DOUBLE', 'DOUBLE'],
            ['REAL', 'REAL'],
            ['DECIMAL(8,2)', 'DECIMAL'],
            ['CHARACTER(3)', 'CHAR'],
            ['NATIONAL CHAR(3)', 'CHAR'],
            ['NCHAR(3)', 'CHAR'],
            ['CHAR VARYING(3)', 'VARCHAR'],
            ['CHARACTER VARYING(3)', 'VARCHAR'],
            ['NATIONAL VARCHAR(3)', 'VARCHAR'],
            ['NVARCHAR(3)', 'VARCHAR'],
            ['NCHAR VARCHAR(3)', 'VARCHAR'],
            ['NATIONAL CHAR VARYING(3)', 'VARCHAR'],
            ['NCHAR VARYING(3)', 'VARCHAR'],
            ['LONG', 'MEDIUMTEXT'],
            ['LONG VARCHAR', 'MEDIUMTEXT'],
            ['LONG VARBINARY', 'MEDIUMBLOB'],
            ['SERIAL', 'BIGINT'],
            ["ENUM('a','b')", 'ENUM'],
            ['VARCHAR(2) CHARACTER SET utf8mb4', 'VARCHAR'],
            ['BIT(1)', 'BIT'],
            ['TIMESTAMP(6)', 'TIMESTAMP'],
            ['BOOL', 'BOOL'],
            ['JSON', 'JSON'],
            ['GEOMETRY', 'GEOMETRY'],
        ];
    }

    public function testExtractEnumValuesDecodesEachMember(): void
    {
        $tree = (new MySqlParser())->parse("CREATE TABLE t (s ENUM('a,b', 'c''d', \"e\", 0x41), n INT)");
        $types = $tree->find('type');

        self::assertSame(['a,b', "c'd", 'e', '0x41'], (new Subject())->extractEnumValues($types[0]));
        self::assertSame([], (new Subject())->extractEnumValues($types[1]));
    }

    public function testParseReadsPrecisionAndScaleForDecimalTypes(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (a DECIMAL(8, 2), b NUMERIC(5), c DEC, d FIXED(4,1))');
        $shapes = array_map(static fn ($type): \SqlFixture\Schema\TypeShape => (new Subject())->parse($type), $tree->find('type'));

        self::assertSame([8, 2, null], [$shapes[0]->precision, $shapes[0]->scale, $shapes[0]->length]);
        self::assertSame([5, 0], [$shapes[1]->precision, $shapes[1]->scale]);
        self::assertSame([null, null, null], [$shapes[2]->precision, $shapes[2]->scale, $shapes[2]->length]);
        self::assertSame(['FIXED', 4, 1], [$shapes[3]->type, $shapes[3]->precision, $shapes[3]->scale]);
    }

    public function testParseReadsTheFirstNumberAsLengthForOtherTypes(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (a VARCHAR(255), b BIT(3), c FLOAT(7,3), d INT, e TIMESTAMP(6))');
        $shapes = array_map(static fn ($type): \SqlFixture\Schema\TypeShape => (new Subject())->parse($type), $tree->find('type'));

        self::assertSame([255, null, null], [$shapes[0]->length, $shapes[0]->precision, $shapes[0]->scale]);
        self::assertSame(3, $shapes[1]->length);
        self::assertSame(['FLOAT', 7], [$shapes[2]->type, $shapes[2]->length]);
        self::assertNull($shapes[3]->length);
        self::assertSame(6, $shapes[4]->length);
        self::assertFalse($shapes[3]->autoIncrement);
    }

    public function testParseMarksSerialAsAutoIncrementBigint(): void
    {
        $tree = (new MySqlParser())->parse('CREATE TABLE t (a SERIAL)');
        $shape = (new Subject())->parse($tree->find('type')[0]);

        self::assertSame('BIGINT', $shape->type);
        self::assertTrue($shape->autoIncrement);
    }
}
