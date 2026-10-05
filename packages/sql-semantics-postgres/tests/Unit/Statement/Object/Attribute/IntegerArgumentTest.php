<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\IntegerArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(IntegerArgument::class)]
#[Small]
final class IntegerArgumentTest extends TestCase
{
    public function testValueKeepsTheSign(): void
    {
        self::assertSame(-16, (new IntegerArgument(new SignedNumber(true, new IntegerConstant('16'))))->value());
    }

    public function testFitsOnlyAnIntegerReading(): void
    {
        $integer = new IntegerArgument(new SignedNumber(false, new IntegerConstant('1')));
        self::assertSame([true, false], [$integer->fits(Reading::Integer), $integer->fits(Reading::Length)]);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new IntegerArgument(new SignedNumber(false, new IntegerConstant('1'))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheNumber(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new IntegerArgument(new SignedNumber(true, new IntegerConstant('2'))))->render($out);
        self::assertSame('- 2', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANumberWithAPoint(): void
    {
        $this->expectExceptionMessage('An integer attribute is an integer that fits in 32 bits.');
        new IntegerArgument(new SignedNumber(false, new NumericConstant('1', '0')));
    }
}
