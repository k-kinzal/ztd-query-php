<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

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
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(SignedNumber::class)]
#[Small]
final class SignedNumberTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new SignedNumber(false, new IntegerConstant('1')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheMinusSign(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SignedNumber(true, new NumericConstant('1', '5')))->render($out);
        self::assertSame('- 1.5', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new SignedNumber(false, new IntegerConstant('7')))->render($second);
        self::assertSame('7', (new Lexical())->join($second->pieces()));
    }
}
