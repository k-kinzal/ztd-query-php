<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(IntegerConstant::class)]
#[Small]
final class IntegerConstantTest extends TestCase
{
    public function testRenderWritesTheDigits(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new IntegerConstant('2147483647'))->render($out);
        self::assertSame('2147483647', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnIntegerBeyond32Bits(): void
    {
        $this->expectExceptionMessage('An integer constant fits 32 bits; a larger integer is a numeric constant.');
        new IntegerConstant('2147483648');
    }

    public function testRejectsASign(): void
    {
        $this->expectExceptionMessage('An integer constant is canonical decimal digits.');
        new IntegerConstant('-1');
    }
}
