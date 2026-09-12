<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\TypeParameters as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TypeShape::class)]
final class TypeParametersTest extends TestCase
{
    public function testIsDecimalTypeRecognizesAliases(): void
    {
        self::assertTrue((new Subject())->isDecimalType('FIXED'));
        self::assertFalse((new Subject())->isDecimalType('INT'));
    }

    public function testIsBitTypeRecognizesOnlyBit(): void
    {
        self::assertTrue((new Subject())->isBitType('BIT'));
        self::assertFalse((new Subject())->isBitType('BINARY'));
    }

    public function testExtractEnumValuesStripsQuotesAndSkipsNonStrings(): void
    {
        self::assertSame(['open', 'closed'], (new Subject())->extractEnumValues(["'open'", '"closed"', 3]));
    }

    public function testParseReadsNativePrecisionAndScale(): void
    {
        $sql = 'CREATE TABLE users (id INT NOT NULL PRIMARY KEY, amount DECIMAL(8, 2) UNSIGNED DEFAULT 12.5)';
        $parser = new \PhpMyAdmin\SqlParser\Parser($sql);
        $statement = $parser->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        self::assertIsArray($statement->fields);
        $type = $statement->fields[1]->type;
        self::assertNotNull($type);
        $shape = (new Subject())->parse($type);
        self::assertSame('DECIMAL', $shape->type);
        self::assertSame(8, $shape->precision);
        self::assertSame(2, $shape->scale);
    }
}
