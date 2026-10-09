<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\Literals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\EscapeRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(Literals::class)]
#[Small]
final class LiteralsTest extends TestCase
{
    public function testSizeAppliesSuffixesWithoutLosingIntegerPrecision(): void
    {
        $literal = new Literals();

        self::assertSame('4194304', $literal->size(new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(null, new \SqlSemantics\Statement\Identifier\Name('4M'))));
        self::assertSame('18446744073709551615', $literal->size(new \SqlSemantics\Platform\MySql\Statement\Literal\ByteSize(new Numeral('18446744073709551615'))));
    }

    public function testBytesDecodesHexadecimalAndBitLiterals(): void
    {
        $literals = new Literals();

        self::assertSame(["\x0f", 'A', 'text'], [$literals->bytes(new Text('f', EscapeRule::Backslash, Radix::Hexadecimal)), $literals->bytes(new Text('1000001', EscapeRule::Backslash, Radix::Bit)), $literals->bytes(new Text('text'))]);
    }

    public function testNumberReadsDecimalHexadecimalAndFloatingNumbers(): void
    {
        $literals = new Literals();

        self::assertSame(['7208', '0', '9644400', '100', '18446744073709551616'], [$literals->number(new Numeral('000007208')), $literals->number(new Numeral('000')), $literals->number(new Numeral('932970', true)), $literals->number(new Numeral('1e2')), $literals->number(new Numeral('18446744073709551616'))]);
    }
}
