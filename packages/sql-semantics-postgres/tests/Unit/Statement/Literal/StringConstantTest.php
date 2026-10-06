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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(StringConstant::class)]
#[Small]
final class StringConstantTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new StringConstant('a'))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderDoublesQuotes(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new StringConstant("it's\\n"))->render($out);
        self::assertSame("'it''s\\n'", (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAZeroByte(): void
    {
        $this->expectExceptionMessage('A PostgreSQL string constant holds no zero byte.');
        new StringConstant("a\0");
    }
}
