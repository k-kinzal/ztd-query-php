<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BitStringRadix;

#[CoversClass(BitStringConstant::class)]
#[Small]
final class BitStringConstantTest extends TestCase
{
    public function testRenderWritesThePrefixAndTheDigits(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BitStringConstant(BitStringRadix::Hexadecimal, '1F'))->render($out);
        self::assertSame("x'1F'", (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAQuoteAmongTheDigits(): void
    {
        $this->expectExceptionMessage('A bit-string constant holds no quote and no zero byte.');
        new BitStringConstant(BitStringRadix::Binary, "1'0");
    }
}
