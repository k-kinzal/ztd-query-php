<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Globals;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

#[CoversClass(Store::class)]
#[Small]
final class StoreTest extends TestCase
{
    public function testValueStoresNullAsNull(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);

        self::assertNull((new Store($context))->value(null, Domain::null(), new ColumnDefinition('c', Domain::integer(), Fill::none())));
    }

    public function testValueClipsAnIntegerOutsideTheRangeOfTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::Tiny, 4), Fill::none());

        $value = (new Store($context, 3))->value(300, Domain::integer(), $column);

        self::assertSame([127, [['Warning', 1264, "Out of range value for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testValueRefusesAnIntegerOutsideTheRangeUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $column = new ColumnDefinition('c', Domain::integer(Field::Tiny, 4), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1264);
        $this->expectExceptionMessage("Out of range value for column 'c' at row 3");

        (new Store($context, 3))->value(300, Domain::integer(), $column);
    }

    public function testAdjustRecordsAWarningOutsideAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);

        (new Store($context))->adjust(ErrorCode::DataTruncated, 'c', 4);

        self::assertSame([['Warning', 1265, "Data truncated for column 'c' at row 4"]], $context->diagnostics->conditions);
    }

    public function testAdjustRaisesAnErrorUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1265);
        $this->expectExceptionMessage("Data truncated for column 'c' at row 4");

        (new Store($context))->adjust(ErrorCode::DataTruncated, 'c', 4);
    }

    public function testIntegerReadsTheLeadingNumberOfAStringWithAWarning(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::Long, 11), Fill::none());
        $from = Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'));

        $value = (new Store($context, 2))->integer('12abc', $from, $column);

        self::assertSame([12, [['Warning', 1265, "Data truncated for column 'c' at row 2"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testIntegerReadsAStringThatIsNoNumberAsZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::Long, 11), Fill::none());
        $from = Domain::string(3, Collation::known('utf8mb4_0900_ai_ci'));

        $value = (new Store($context, 2))->integer('abc', $from, $column);

        self::assertSame([0, [['Warning', 1366, "Incorrect integer value: 'abc' for column 'c' at row 2"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testIntegerRoundsADoubleHalfToEven(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::Long, 11), Fill::none());

        self::assertSame([2, 4, -2], [(new Store($context))->integer(2.5, Domain::double(), $column), (new Store($context))->integer(3.5, Domain::double(), $column), (new Store($context))->integer(-2.5, Domain::double(), $column)]);
    }

    public function testIntegerRoundsADecimalHalfAwayFromZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::Long, 11), Fill::none());

        self::assertSame([3, -3], [(new Store($context))->integer('2.5', Domain::decimal(2, 1), $column), (new Store($context))->integer('-2.5', Domain::decimal(2, 1), $column)]);
    }

    public function testIntegerHoldsTheLargestUnsignedBigintInTheSameBits(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::LongLong, 20, true), Fill::none());
        $from = Domain::string(20, Collation::known('utf8mb4_0900_ai_ci'));

        self::assertSame([-1, []], [(new Store($context))->integer('18446744073709551615', $from, $column), $context->diagnostics->conditions]);
    }

    public function testIntegerClipsANegativeValueOfAnUnsignedColumnToZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::integer(Field::Tiny, 3, true), Fill::none());

        self::assertSame([0, 1264], [(new Store($context))->integer(-1, Domain::integer(), $column), $context->diagnostics->conditions[0][1]]);
    }

    public function testRangeAnswersTheBoundsOfEachIntegerType(): void
    {
        $store = new Store(new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0));

        self::assertSame([['-128', '127'], ['0', '16777215'], ['-2147483648', '2147483647'], ['-9223372036854775808', '9223372036854775807'], ['0', '18446744073709551615']], [$store->range(Domain::integer(Field::Tiny, 4)), $store->range(Domain::integer(Field::Int24, 8, true)), $store->range(Domain::integer(Field::Long, 11)), $store->range(Domain::integer()), $store->range(Domain::integer(Field::LongLong, 20, true))]);
    }

    public function testDecimalRoundsExtraDecimalsWithANote(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::decimal(5, 2), Fill::none());

        $value = (new Store($context, 3))->decimal('1.235', Domain::decimal(5, 3), $column);

        self::assertSame(['1.24', [['Note', 1265, "Data truncated for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testDecimalRoundsExtraDecimalsUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $column = new ColumnDefinition('c', Domain::decimal(5, 2), Fill::none());

        $value = (new Store($context))->decimal('-1.235', Domain::decimal(5, 3), $column);

        self::assertSame(['-1.24', 'Note'], [$value, $context->diagnostics->conditions[0][0]]);
    }

    public function testDecimalClipsToTheLargestValueOfTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::decimal(5, 2), Fill::none());
        $store = new Store($context, 3);

        self::assertSame(['999.99', '-999.99', 2], [$store->decimal(1000, Domain::integer(), $column), $store->decimal(-1000, Domain::integer(), $column), count($context->diagnostics->conditions)]);
    }

    public function testDecimalClipsANegativeValueOfAnUnsignedColumnToZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::decimal(5, 2, true), Fill::none());

        $value = (new Store($context, 3))->decimal(-1, Domain::integer(), $column);

        self::assertSame(['0.00', [['Warning', 1264, "Out of range value for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testDecimalReadsAStringThatIsNoNumberAsZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::decimal(5, 2), Fill::none());
        $from = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));

        $value = (new Store($context, 3))->decimal('x', $from, $column);

        self::assertSame(['0.00', [['Warning', 1366, "Incorrect decimal value: 'x' for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testDecimalReadsTheLeadingNumberOfAString(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::decimal(5, 2), Fill::none());
        $from = Domain::string(4, Collation::known('utf8mb4_0900_ai_ci'));

        $value = (new Store($context, 3))->decimal('1.5x', $from, $column);

        self::assertSame(['1.50', [['Warning', 1265, "Data truncated for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testRealReadsAStringThatIsNoNumberAsZero(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::double(), Fill::none());
        $from = Domain::string(1, Collation::known('utf8mb4_0900_ai_ci'));

        $value = (new Store($context, 3))->real('x', $from, $column);

        self::assertSame([0.0, [['Warning', 1366, "Incorrect double value: 'x' for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testRealHoldsAFloatInSinglePrecision(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED), Fill::none());

        self::assertSame(0.10000000149011612, (new Store($context))->real(0.1, Domain::double(), $column));
    }

    public function testRealClipsAFloatOutsideTheSingleRange(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Double, Field::Float, 12, Domain::NOT_FIXED), Fill::none());

        $value = (new Store($context, 3))->real(-1e39, Domain::double(), $column);

        self::assertSame([-3.4028234663852886e38, [['Warning', 1264, "Out of range value for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testRealRoundsToTheDecimalsOfTheColumn(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Double, Field::Double, 5, 2), Fill::none());

        self::assertSame(1.24, (new Store($context))->real(1.236, Domain::double(), $column));
    }

    public function testStringCutsATooLongValueWithAWarning(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', Domain::string(2, $collation), Fill::none());

        $value = (new Store($context, 3))->string('ééé', Domain::string(3, $collation), $column);

        self::assertSame(['éé', [['Warning', 1265, "Data truncated for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testStringRefusesATooLongValueUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', Domain::string(3, $collation), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1406);
        $this->expectExceptionMessage("Data too long for column 'c' at row 3");

        (new Store($context, 3))->string('abcd', Domain::string(4, $collation), $column);
    }

    public function testStringCutsTrailingSpacesWithANoteEvenUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', Domain::string(3, $collation), Fill::none());

        $value = (new Store($context, 3))->string('abc  ', Domain::string(5, $collation), $column);

        self::assertSame(['abc', [['Note', 1265, "Data truncated for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testStringStripsTrailingSpacesOfAChar(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $column = new ColumnDefinition('c', Domain::string(5, $collation, Field::String), Fill::none());

        self::assertSame('ab', (new Store($context))->string('ab  ', Domain::string(4, $collation), $column));
    }

    public function testStringPadsABinaryWithZeroBytes(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::string(4, Collation::binary(), Field::String), Fill::none());

        self::assertSame("ab\0\0", (new Store($context))->string('ab', Domain::string(2, Collation::binary()), $column));
    }

    public function testStringWritesANumberAsText(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), Fill::none());

        self::assertSame('42', (new Store($context))->string(42, Domain::integer(), $column));
    }

    public function testBitWritesTheBytesOfTheNumber(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Bit, Field::Bit, 10, 0, true), Fill::none());

        self::assertSame("\x02\x01", (new Store($context))->bit(513, Domain::integer(), $column));
    }

    public function testBitClipsANumberOfMoreBits(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0);
        $column = new ColumnDefinition('c', new Domain(Kind::Bit, Field::Bit, 4, 0, true), Fill::none());

        $value = (new Store($context, 3))->bit(16, Domain::integer(), $column);

        self::assertSame(["\x0f", [['Warning', 1264, "Out of range value for column 'c' at row 3"]]], [$value, $context->diagnostics->conditions]);
    }

    public function testBitRefusesANumberOfMoreBitsUnderAStrictMode(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), new Variables(SystemVariables::of(GrammarRelease::MySql847), new Globals()), 0.0, true);
        $column = new ColumnDefinition('c', new Domain(Kind::Bit, Field::Bit, 4, 0, true), Fill::none());

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1406);
        $this->expectExceptionMessage("Data too long for column 'c' at row 3");

        (new Store($context, 3))->bit(16, Domain::integer(), $column);
    }
}
