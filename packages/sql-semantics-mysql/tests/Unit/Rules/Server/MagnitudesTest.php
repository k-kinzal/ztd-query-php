<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\Magnitudes;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

#[CoversClass(Magnitudes::class)]
#[Medium]
final class MagnitudesTest extends TestCase
{
    public function testBytesCountsTheBytesOfEachSpelling(): void
    {
        $magnitudes = new Magnitudes();

        self::assertSame([3, 2, 1], [$magnitudes->bytes(new Text('abc')), $magnitudes->bytes(new Text('abc', EscapeRule::Backslash, Radix::Hexadecimal)), $magnitudes->bytes(new Text('101', EscapeRule::Backslash, Radix::Bit))]);
    }

    public function testIntegralTellsWhetherANumberIsAnInteger(): void
    {
        self::assertTrue((new Magnitudes())->integral(new Numeral('ff', true)));
        self::assertFalse((new Magnitudes())->integral(new Numeral('1.0')));
    }

    public function testIntegerReportsAFractionalNumber(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP SPATIAL REFERENCE SYSTEM 1e2');

        self::assertInstanceOf(FractionalNumber::class, $operation->facts->diagnostics[0]);
    }

    public function testAtMostComparesTheDigitsExactly(): void
    {
        $magnitudes = new Magnitudes();

        self::assertTrue($magnitudes->atMost(new Numeral('0009223372036854775807'), '9223372036854775807'));
        self::assertTrue($magnitudes->atMost(new Numeral('8000000000000000', true), '9223372036854775807'));
        self::assertFalse($magnitudes->atMost(new Numeral('8000000000000000', true), '9223372036854775806'));
        self::assertFalse($magnitudes->atMost(new Numeral('100000000', true), '4294967295'));
        self::assertTrue($magnitudes->atMost(new Numeral('12.9'), '12'));
    }

    public function testDecimalConvertsHexadecimalDigits(): void
    {
        self::assertSame(['255', '0', '18446744073709551615'], [(new Magnitudes())->decimal('FF'), (new Magnitudes())->decimal('000'), (new Magnitudes())->decimal('ffffffffffffffff')]);
    }

    public function testFormatTakesAnyNumberOnMySql56Only(): void
    {
        $magnitudes = new Magnitudes();

        self::assertTrue($magnitudes->format(new Numeral('9223372036854775808'), GrammarRelease::MySql5651));
        self::assertFalse($magnitudes->format(new Numeral('9223372036854775808'), GrammarRelease::MySql5744));
        self::assertTrue($magnitudes->format(new Numeral('9223372036854775807'), GrammarRelease::MySql847));
        self::assertTrue($magnitudes->format(new Numeral('ffffffffffffffffff', true), GrammarRelease::MySql847));
    }

    public function testIdentifierRefusesAFormatTheReleaseRejects(): void
    {
        $xid = new Xid(new Text('a'), new Text('b'), new Numeral('18446744073709551615'));
        (new Magnitudes())->identifier($xid, GrammarRelease::MySql5651);
        (new Magnitudes())->identifier(new Xid(new Text('a')), GrammarRelease::MySql847);

        $this->expectExceptionMessage('A format identifier is at most 9223372036854775807 from MySQL 5.7 on.');

        (new Magnitudes())->identifier($xid, GrammarRelease::MySql847);
    }
}
