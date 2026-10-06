<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(NumericConstant::class)]
#[Small]
final class NumericConstantTest extends TestCase
{
    public function testRenderWritesTheTextAsWritten(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NumericConstant('0001.50'))->render($out);
        self::assertSame('0001.50', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NumericConstant('0x1FFFFFFFFF'))->render($second);
        self::assertSame('0x1FFFFFFFFF', (new Lexical())->join($second->pieces()));
    }

    public function testKeepsTheSpellingsTheServerKeepsApart(): void
    {
        self::assertSame(['1e2', '100.', '.5', '1_000_000_000_000'], [(new NumericConstant('1e2'))->text, (new NumericConstant('100.'))->text, (new NumericConstant('.5'))->text, (new NumericConstant('1_000_000_000_000'))->text]);
    }

    public function testRejectsAnIntegerThatFits32Bits(): void
    {
        $this->expectExceptionMessage('A numeric constant is a number with a point or an exponent, or an integer beyond 32 bits, as the scanner reads it.');
        new NumericConstant('0x7FFFFFFF');
    }

    public function testRejectsASign(): void
    {
        $this->expectExceptionMessage('A numeric constant is a number with a point or an exponent, or an integer beyond 32 bits, as the scanner reads it.');
        new NumericConstant('-1.5');
    }

    public function testRejectsAnExponentWithoutDigits(): void
    {
        $this->expectExceptionMessage('A numeric constant is a number with a point or an exponent, or an integer beyond 32 bits, as the scanner reads it.');
        new NumericConstant('1e');
    }
}
