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
    public function testRenderAlwaysWritesTheDecimalPoint(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NumericConstant('100'))->render($out);
        self::assertSame('100.', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NumericConstant('1', '50', '-3'))->render($second);
        self::assertSame('1.50e-3', (new Lexical())->join($second->pieces()));
    }

    public function testRejectsLeadingZerosOfTheIntegerDigits(): void
    {
        $this->expectExceptionMessage('The integer digits of a numeric constant are canonical decimal digits.');
        new NumericConstant('01', '5');
    }

    public function testRejectsAnExponentWithAPlusSign(): void
    {
        $this->expectExceptionMessage('The exponent of a numeric constant is a canonical integer other than zero.');
        new NumericConstant('1', '5', '+3');
    }
}
