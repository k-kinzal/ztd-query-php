<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineLanguage;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoutineLanguage::class)]
#[Small]
final class RoutineLanguageTest extends TestCase
{
    public function testNameReadsWordsAndStrings(): void
    {
        self::assertSame(['sql', 'C'], [(new RoutineLanguage(new Word(new Name('sql'))))->name(), (new RoutineLanguage(new StringConstant('C')))->name()]);
    }

    public function testAlterableIsFalse(): void
    {
        self::assertFalse((new RoutineLanguage(new Word(new Name('sql'))))->alterable());
    }

    public function testSettingIsLanguage(): void
    {
        self::assertSame('language', (new RoutineLanguage(new Word(new Name('sql'))))->setting());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new RoutineLanguage(new Word(new Name('sql'))))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheLanguage(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new RoutineLanguage(new StringConstant('plpgsql')))->render($out);
        self::assertSame("LANGUAGE 'plpgsql'", (new Lexical())->join($out->pieces()));
    }
}
