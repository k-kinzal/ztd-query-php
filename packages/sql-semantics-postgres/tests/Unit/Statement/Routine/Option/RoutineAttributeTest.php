<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineAttribute;

#[CoversClass(RoutineAttribute::class)]
#[Small]
final class RoutineAttributeTest extends TestCase
{
    public function testAlterableExcludesWindow(): void
    {
        self::assertSame([true, false], [RoutineAttribute::Strict->alterable(), RoutineAttribute::Window->alterable()]);
    }

    public function testSettingGroupsTheSpellingsOfOneAttribute(): void
    {
        self::assertSame(['strict', 'strict', 'volatility', 'security', 'leakproof', 'window'], [RoutineAttribute::CalledOnNullInput->setting(), RoutineAttribute::Strict->setting(), RoutineAttribute::Stable->setting(), RoutineAttribute::SecurityInvoker->setting(), RoutineAttribute::NotLeakproof->setting(), RoutineAttribute::Window->setting()]);
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        RoutineAttribute::Immutable->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheKeywords(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        RoutineAttribute::ReturnsNullOnNullInput->render($out);
        self::assertSame('RETURNS NULL ON NULL INPUT', (new Lexical())->join($out->pieces()));
    }
}
