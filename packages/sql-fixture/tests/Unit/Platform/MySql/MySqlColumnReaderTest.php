<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PhpMyAdmin\SqlParser\Components\OptionsArray;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\MySqlColumnReader;
use SqlFixture\Schema\ColumnDefinition;
use Tests\Fixture\Platform\MySqlDefinition;

#[CoversClass(MySqlColumnReader::class)]
#[UsesClass(ColumnDefinition::class)]
final class MySqlColumnReaderTest extends TestCase
{
    #[Test]
    public function testReadTakesTheSingleParenthesizedNumberAsALength(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c VARCHAR(20))');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertSame('VARCHAR', $column->type);
        self::assertSame(20, $column->length);
        self::assertNull($column->precision);
    }

    #[Test]
    #[DataProvider('providerExactNumericType')]
    public function testReadTakesTheParenthesizedNumbersOfAnExactNumericAsPrecisionAndScale(string $type): void
    {
        $column = MySqlDefinition::firstColumnOf("CREATE TABLE t (c {$type}(8, 3))");

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertSame(8, $column->precision);
        self::assertSame(3, $column->scale);
        self::assertNull($column->length);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerExactNumericType(): array
    {
        return [['DECIMAL'], ['NUMERIC'], ['DEC'], ['FIXED']];
    }

    #[Test]
    public function testReadTakesTheScaleOfAnExactNumericWrittenWithoutOneAsZero(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c DECIMAL(8))');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertSame(8, $column->precision);
        self::assertSame(0, $column->scale);
    }

    #[Test]
    public function testReadLeavesAnExactNumericWrittenWithoutNumbersUnmeasured(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c DECIMAL)');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertNull($column->precision);
        self::assertNull($column->scale);
    }

    #[Test]
    public function testReadTakesTheNumberOfABitAsAWidth(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c BIT(4))');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertSame(4, $column->length);
    }

    #[Test]
    public function testReadIsNullForADefinitionThatDeclaresNoType(): void
    {
        $definition = MySqlDefinition::definitionOf('CREATE TABLE t (id INT, PRIMARY KEY (id))', 1);

        self::assertNull((new MySqlColumnReader())->read($definition, '', []));
    }

    #[Test]
    public function testReadSeesUnsignedWhereverMySqlRecordedIt(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c INT UNSIGNED)');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertTrue($column->unsigned);
    }

    #[Test]
    public function testReadTakesAKeyDeclaredOnItsOwnLineAsBindingTheColumnItNames(): void
    {
        $definition = MySqlDefinition::definitionOf('CREATE TABLE t (id INT, PRIMARY KEY (id))', 0);

        $column = (new MySqlColumnReader())->read($definition, 'id', ['id']);

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertFalse($column->nullable);
    }

    #[Test]
    public function testReadTakesAKeyDeclaredBesideItsColumnAsBindingIt(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (id INT PRIMARY KEY)');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertFalse($column->nullable);
    }

    #[Test]
    public function testReadSeesAColumnTheServerFillsIn(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c INT AS (1 + 1))');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertTrue($column->generated);
    }

    #[Test]
    public function testReadSeesAColumnTheServerNumbers(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY)');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertTrue($column->autoIncrement);
    }

    #[Test]
    public function testReadNamesTheMembersOfAnEnumeration(): void
    {
        $column = MySqlDefinition::firstColumnOf("CREATE TABLE t (c ENUM('gold', 'silver'))");

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertSame(['gold', 'silver'], $column->enumValues);
    }

    #[Test]
    public function testReadNamesNoMembersForATypeThatDeclaresNone(): void
    {
        $column = MySqlDefinition::firstColumnOf('CREATE TABLE t (c INT)');

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertNull($column->enumValues);
    }

    #[Test]
    #[DataProvider('providerDefaultAsWritten')]
    public function testDefaultValueReadsADefaultAsTheTypeItIsWrittenAs(string $written, mixed $expected): void
    {
        $column = MySqlDefinition::firstColumnOf("CREATE TABLE t (c VARCHAR(20) DEFAULT {$written})");

        self::assertInstanceOf(ColumnDefinition::class, $column);
        self::assertSame($expected, $column->default);
    }

    /**
     * @return list<array{string, int|float|string|bool|null}>
     */
    public static function providerDefaultAsWritten(): array
    {
        return [
            ["'ready'", 'ready'],
            ['"ready"', 'ready'],
            ['7', 7],
            ['1.5', 1.5],
            ['NULL', null],
            ['TRUE', true],
            ['FALSE', false],
        ];
    }

    #[Test]
    public function testDefaultValueIsNullWhereNoDefaultWasDeclared(): void
    {
        self::assertNull((new MySqlColumnReader())->defaultValue(null));
    }

    #[Test]
    public function testDefaultValueIsNullWhereTheOptionsDeclareNoDefault(): void
    {
        self::assertNull((new MySqlColumnReader())->defaultValue(new OptionsArray()));
    }

    #[Test]
    public function testEnumMembersTakesTheQuotesOffEveryMemberDeclared(): void
    {
        self::assertSame(['gold', 'silver'], (new MySqlColumnReader())->enumMembers(["'gold'", '"silver"']));
    }

    #[Test]
    public function testEnumMembersLeavesOutAnythingThatIsNotWrittenAsText(): void
    {
        self::assertSame(['gold'], (new MySqlColumnReader())->enumMembers(["'gold'", 7, null]));
    }

}
