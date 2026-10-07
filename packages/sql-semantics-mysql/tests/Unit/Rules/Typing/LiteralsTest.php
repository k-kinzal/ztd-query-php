<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Typing\Literals;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Literals::class)]
#[Small]
final class LiteralsTest extends TestCase
{
    public function testOfReadsTheSessionAndTheReleaseOfAContext(): void
    {
        $settings = new Settings(Collation::known('latin1_bin'));
        $literals = Literals::of((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([], true, null, $settings));

        self::assertSame($settings, $literals->settings);
        self::assertSame(GrammarRelease::MySql5744, $literals->release);
    }

    public function testNumberCountsTheWrittenDigitsOfAnInteger(): void
    {
        $literals = $this->literals();

        self::assertEquals(Domain::integer(Field::LongLong, 4), $literals->number(new NumberLiteral('123')));
        self::assertEquals(Domain::integer(Field::LongLong, 7), $literals->number(new NumberLiteral('000123')));
        self::assertEquals(Domain::integer(Field::LongLong, 19, true), $literals->number(new NumberLiteral('9223372036854775808')));
        self::assertEquals(Domain::integer(Field::LongLong, 20), $literals->number(new NumberLiteral('9223372036854775808'), true));
        self::assertEquals(Domain::decimal(20, 0), $literals->number(new NumberLiteral('18446744073709551616')));
    }

    public function testNumberKeepsTheZeroBeforeThePointOfADecimal(): void
    {
        $literals = $this->literals();

        self::assertSame(4, $literals->number(new NumberLiteral('0.5'))->length);
        self::assertSame(4, $literals->number(new NumberLiteral('000.5'))->length);
        self::assertSame(3, $literals->number(new NumberLiteral('.5'))->length);
        self::assertSame(6, $literals->number(new NumberLiteral('0010.5'))->length);
        self::assertSame(8, $literals->number(new NumberLiteral('0001000.5'))->length);
        self::assertSame(7, $literals->number(new NumberLiteral('1000.5'))->length);
        self::assertSame(2, $literals->number(new NumberLiteral('1.'))->length);
        self::assertEquals(Domain::double(10), $literals->number(new NumberLiteral('123.456e-2')));
    }

    public function testWithinComparesDigitsWithABound(): void
    {
        self::assertTrue($this->literals()->within('99', '100'));
        self::assertTrue($this->literals()->within('100', '100'));
        self::assertFalse($this->literals()->within('101', '100'));
    }

    public function testStringTakesTheConnectionTheIntroducerOrTheNationalCollation(): void
    {
        $literals = $this->literals();

        self::assertEquals(Domain::string(2, Collation::known('latin1_bin'), Field::VarString, Coercibility::Coercible), $literals->string(new StringLiteral(["\xe9\xe9"])));
        self::assertSame('utf8mb4_0900_ai_ci', $literals->string(new StringLiteral(['x'], introducer: new Name('utf8mb4')))->collation->name);
        self::assertSame('utf8mb3_general_ci', $literals->string(new StringLiteral(['x'], national: true))->collation->name);
    }

    public function testIntroducedFallsBackToBinaryForAnUnknownCharacterSet(): void
    {
        self::assertSame('latin1_swedish_ci', $this->literals()->introduced('latin1')->name);
        self::assertSame(Collation::binary(), $this->literals()->introduced('klingon'));
    }

    public function testRadixCountsBytes(): void
    {
        $literals = $this->literals();

        self::assertEquals(Domain::string(2, Collation::binary(), Field::VarString, Coercibility::Coercible), $literals->radix(new RadixLiteral(Radix::Hexadecimal, 'ABC')));
        self::assertSame(2, $literals->radix(new RadixLiteral(Radix::Bit, '100000000'))->length);
        self::assertSame('latin1_swedish_ci', $literals->radix(new RadixLiteral(Radix::Bit, '1', new Name('latin1')))->collation->name);
    }

    public function testTemporalKeepsUpToSixFractionalDigits(): void
    {
        $literals = $this->literals();

        self::assertSame([10, 0], [$literals->temporal(new TemporalLiteral(TemporalForm::Date, '2024-01-02'))->length, 0]);
        self::assertSame([12, 3], [$literals->temporal(new TemporalLiteral(TemporalForm::Time, '10:00:00.123'))->length, $literals->temporal(new TemporalLiteral(TemporalForm::Time, '10:00:00.123'))->decimals]);
        self::assertSame(26, $literals->temporal(new TemporalLiteral(TemporalForm::Timestamp, '2024-01-02 00:00:00.1234567'))->length);
    }

    public function testBooleanIsABigIntOfOneDigit(): void
    {
        self::assertEquals(Domain::integer(Field::LongLong, 1), $this->literals()->boolean());
    }

    public function literals(): Literals
    {
        return new Literals(new Settings(Collation::known('latin1_bin')), GrammarRelease::MySql847);
    }
}
