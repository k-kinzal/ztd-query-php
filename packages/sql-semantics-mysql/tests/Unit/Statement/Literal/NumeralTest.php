<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(Numeral::class)]
#[Small]
final class NumeralTest extends TestCase
{
    public function testRenderWritesTheExactDecimalText(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Numeral('007'))->render($out);
        (new Numeral('1.5'))->render($out);
        (new Numeral('1e2'))->render($out);

        self::assertSame('007 1.5 1e2', (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesAnEvenCountOfHexadecimalDigits(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Numeral('10', true))->render($out);
        (new Numeral('', true))->render($out);

        self::assertSame("x'10' x''", (new Lexical())->join($out->pieces()));
    }

    public function testRenderPrefixesAnOddCountOfHexadecimalDigits(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new Numeral('1', true))->render($out);

        self::assertSame('0x1', (new Lexical())->join($out->pieces()));
    }

    public function testLoweredNumeralKeepsLeadingZeros(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse('ALTER TABLE t COALESCE PARTITION 007')->find('real_ulong_num')[0];
        $numeral = (new Lowering($platform->productions($profile), new Leaves(), $profile))->numbers->numeral($node);

        self::assertSame('007', $numeral->text);
        self::assertFalse($numeral->hexadecimal);
    }

    public function testLoweredHexadecimalNumeralKeepsTheDigitsOfEitherSpelling(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $prefixed = $lowering->numbers->numeral($parser->parse('CHANGE MASTER TO MASTER_PORT = 0xCE6')->find('ulong_num')[0]);
        $quoted = $lowering->numbers->numeral($parser->parse("ALTER TABLE t COALESCE PARTITION x'0a'")->find('real_ulong_num')[0]);
        $out = new Output($platform->codec($profile));
        $prefixed->render($out);
        $quoted->render($out);

        self::assertSame('CE6', $prefixed->text);
        self::assertTrue($prefixed->hexadecimal);
        self::assertSame('0a', $quoted->text);
        self::assertTrue($quoted->hexadecimal);
        self::assertSame("0xCE6 x'0a'", (new Lexical())->join($out->pieces()));
    }

    public function testLoweredDecimalAndFloatNumeralsAreKeptAsWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $decimal = $lowering->numbers->numeral($parser->parse('ALTER TABLE t COALESCE PARTITION 1.5')->find('real_ulong_num')[0]);
        $float = $lowering->numbers->numeral($parser->parse('ALTER TABLE t COALESCE PARTITION 1e2')->find('real_ulong_num')[0]);

        self::assertSame('1.5', $decimal->text);
        self::assertSame('1e2', $float->text);
    }

    public function testRejectsASignedNumber(): void
    {
        $this->expectExceptionMessage('A numeral is an unsigned number or the digits of a hexadecimal literal.');

        new Numeral('-1');
    }

    public function testRejectsAWordAsADecimalNumber(): void
    {
        $this->expectExceptionMessage('A numeral is an unsigned number or the digits of a hexadecimal literal.');

        new Numeral('16M');
    }

    public function testRejectsNonHexadecimalDigits(): void
    {
        $this->expectExceptionMessage('A numeral is an unsigned number or the digits of a hexadecimal literal.');

        new Numeral('zz', true);
    }
}
