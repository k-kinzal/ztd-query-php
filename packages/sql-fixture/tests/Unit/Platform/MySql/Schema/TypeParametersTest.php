<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\TypeParameters as Subject;
use SqlFixture\Schema\TypeShape;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use Tests\Statement\MySqlStatements;

#[CoversClass(Subject::class)]
#[UsesClass(TypeShape::class)]
final class TypeParametersTest extends TestCase
{
    #[DataProvider('providerTypeNames')]
    public function testShapeNamesTheTypeAsTheServerDoes(string $declaration, string $expected): void
    {
        self::assertSame($expected, (new Subject())->shape(MySqlStatements::types("c {$declaration}")[0])->type);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerTypeNames(): array
    {
        return [
            ['int(11) unsigned', 'INT'],
            ['INTEGER', 'INT'],
            ['DOUBLE PRECISION', 'DOUBLE'],
            ['REAL', 'REAL'],
            ['FLOAT(7,3)', 'FLOAT'],
            ['FLOAT(24)', 'FLOAT'],
            ['FLOAT(25)', 'DOUBLE'],
            ['DECIMAL(8,2)', 'DECIMAL'],
            ['NUMERIC(5)', 'DECIMAL'],
            ['FIXED', 'DECIMAL'],
            ['CHARACTER(3)', 'CHAR'],
            ['NATIONAL CHAR(3)', 'CHAR'],
            ['CHAR VARYING(3)', 'VARCHAR'],
            ['NVARCHAR(3)', 'VARCHAR'],
            ['LONG', 'MEDIUMTEXT'],
            ['LONG VARCHAR', 'MEDIUMTEXT'],
            ['LONG CHAR VARYING', 'MEDIUMTEXT'],
            ['LONG VARBINARY', 'MEDIUMBLOB'],
            ['VARBINARY(4)', 'VARBINARY'],
            ['SERIAL', 'BIGINT'],
            ["ENUM('a','b')", 'ENUM'],
            ["SET('a','b')", 'SET'],
            ['VARCHAR(2) CHARACTER SET utf8mb4', 'VARCHAR'],
            ['BIT(1)', 'BIT'],
            ['TIMESTAMP(6)', 'TIMESTAMP'],
            ['YEAR', 'YEAR'],
            ['BOOL', 'BOOLEAN'],
            ['BOOLEAN', 'BOOLEAN'],
            ['JSON', 'JSON'],
            ['GEOMETRY', 'GEOMETRY'],
            ['POINT', 'POINT'],
        ];
    }

    public function testShapeReadsPrecisionAndScaleForDecimalTypes(): void
    {
        $shapes = array_map(static fn (TypeName $type): TypeShape => (new Subject())->shape($type), MySqlStatements::types('a DECIMAL(8, 2), b NUMERIC(5), c DEC, d FIXED(4,1)'));

        self::assertSame([8, 2, null], [$shapes[0]->precision, $shapes[0]->scale, $shapes[0]->length]);
        self::assertSame([5, 0], [$shapes[1]->precision, $shapes[1]->scale]);
        self::assertSame([null, null, null], [$shapes[2]->precision, $shapes[2]->scale, $shapes[2]->length]);
        self::assertSame([4, 1], [$shapes[3]->precision, $shapes[3]->scale]);
    }

    public function testShapeReadsOneNumberAsALengthAndTwoAsAPrecisionAndScale(): void
    {
        $shapes = array_map(static fn (TypeName $type): TypeShape => (new Subject())->shape($type), MySqlStatements::types('a VARCHAR(255), b BIT(3), c FLOAT(7,3), d INT, e TIMESTAMP(6), f INT(11), g BINARY(16), h FLOAT(40)'));

        self::assertSame([255, null, null], [$shapes[0]->length, $shapes[0]->precision, $shapes[0]->scale]);
        self::assertSame(3, $shapes[1]->length);
        self::assertSame([null, 7, 3], [$shapes[2]->length, $shapes[2]->precision, $shapes[2]->scale]);
        self::assertNull($shapes[3]->length);
        self::assertSame(6, $shapes[4]->length);
        self::assertSame(11, $shapes[5]->length);
        self::assertSame(16, $shapes[6]->length);
        self::assertSame(['DOUBLE', null, null], [$shapes[7]->type, $shapes[7]->length, $shapes[7]->precision]);
        self::assertFalse($shapes[3]->autoIncrement);
    }

    public function testShapeMarksSerialAsAutoIncrementBigint(): void
    {
        $shape = (new Subject())->shape(MySqlStatements::types('a SERIAL')[0]);

        self::assertSame('BIGINT', $shape->type);
        self::assertTrue($shape->autoIncrement);
    }

    public function testUnsignedReadsUnsignedZerofillAndSerial(): void
    {
        $unsigned = array_map(static fn (TypeName $type): bool => (new Subject())->unsigned($type), MySqlStatements::types('a INT UNSIGNED, b INT ZEROFILL, c INT, d DECIMAL(5,2) UNSIGNED, e DOUBLE ZEROFILL, f FLOAT, g SERIAL, h BOOL, i VARCHAR(3)'));

        self::assertSame([true, true, false, true, true, false, true, false, false], $unsigned);
    }

    public function testNumericTellsNumberTypesFromOthers(): void
    {
        $numeric = array_map(static fn (TypeName $type): bool => (new Subject())->numeric($type), MySqlStatements::types('a INT, b DECIMAL, c DOUBLE, d BIT(8), e BOOL, f SERIAL, g VARCHAR(3), h BLOB, i JSON, j DATE'));

        self::assertSame([true, true, true, true, true, true, false, false, false, false], $numeric);
    }

    public function testMembersDecodesEachMember(): void
    {
        $types = MySqlStatements::types("s ENUM('a,b', 'c''d', \"e\", 0x41, X'4243', b'01000100', 'f\\\\g', 'h  ', ' i'), t SET('x'), n INT");

        self::assertSame(['a,b', "c'd", 'e', 'A', 'BC', 'D', 'f\\g', 'h', ' i'], (new Subject())->members($types[0]));
        self::assertSame(['x'], (new Subject())->members($types[1]));
        self::assertNull((new Subject())->members($types[2]));
    }

    public function testMemberPadsDigitsToWholeBytes(): void
    {
        self::assertSame("\x0A", (new Subject())->member(new Text('A', radix: Radix::Hexadecimal)));
        self::assertSame("\x01\x00", (new Subject())->member(new Text('100000000', radix: Radix::Bit)));
        self::assertSame('plain', (new Subject())->member(new Text('plain')));
    }

    public function testCharacterNameFoldsSynonyms(): void
    {
        self::assertSame('VARCHAR', (new Subject())->characterName(CharacterKind::CharVarying));
        self::assertSame('MEDIUMTEXT', (new Subject())->characterName(CharacterKind::Long));
        self::assertSame('MEDIUMTEXT', (new Subject())->characterName(CharacterKind::LongVarChar));
        self::assertSame('MEDIUMTEXT', (new Subject())->characterName(CharacterKind::LongCharVarying));
        self::assertSame('TINYTEXT', (new Subject())->characterName(CharacterKind::TinyText));
    }

    public function testNumbersKeepsTheWrittenSizesInOrder(): void
    {
        self::assertSame([10, 2], (new Subject())->numbers('10', '2'));
        self::assertSame([10], (new Subject())->numbers('10', null));
        self::assertSame([], (new Subject())->numbers(null, null));
    }
}
