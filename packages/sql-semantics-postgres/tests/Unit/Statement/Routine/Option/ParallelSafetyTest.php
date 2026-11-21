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
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ParallelSafety;

#[CoversClass(ParallelSafety::class)]
#[Small]
final class ParallelSafetyTest extends TestCase
{
    public function testValidAcceptsTheThreeLevels(): void
    {
        self::assertSame([true, true, false], [(new ParallelSafety(new Name('restricted')))->valid(), (new ParallelSafety(new Name('unsafe')))->valid(), (new ParallelSafety(new Name('Safe')))->valid()]);
    }

    public function testAlterableIsTrue(): void
    {
        self::assertTrue((new ParallelSafety(new Name('safe')))->alterable());
    }

    public function testSettingIsParallel(): void
    {
        self::assertSame('parallel', (new ParallelSafety(new Name('safe')))->setting());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new ParallelSafety(new Name('bogus')))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheLevel(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ParallelSafety(new Name('safe')))->render($out);
        self::assertSame('PARALLEL safe', (new Lexical())->join($out->pieces()));
    }
}
