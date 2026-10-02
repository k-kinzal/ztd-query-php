<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(KeywordWord::class)]
#[Small]
final class KeywordWordTest extends TestCase
{
    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new KeywordWord(new Name('none')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheKeywordBare(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new KeywordWord(new Name('true')))->render($out);
        self::assertSame('TRUE', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAWordThatIsNotReserved(): void
    {
        $this->expectExceptionMessage('A keyword value is a reserved keyword or none.');
        new KeywordWord(new Name('abort'));
    }
}
