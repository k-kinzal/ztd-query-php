<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBound;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameExclusion;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameMode;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowFrame;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(WindowFrame::class)]
#[Small]
final class WindowFrameTest extends TestCase
{
    public function testDeriveClauseDerivesTheOffsetsOfBothBounds(): void
    {
        $scalar = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new WindowFrame(FrameMode::Rows, new FrameBound(FrameBoundKind::CurrentRow), new FrameBound(FrameBoundKind::OffsetFollowing, $scalar)))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($scalar));
    }

    public function testRenderWritesBetweenForTwoBounds(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new WindowFrame(FrameMode::Groups, new FrameBound(FrameBoundKind::OffsetPreceding, new Constant(new IntegerConstant('1'))), new FrameBound(FrameBoundKind::CurrentRow), FrameExclusion::Ties))->render($out);
        self::assertSame('GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES', (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesAStartAlone(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new WindowFrame(FrameMode::Range, new FrameBound(FrameBoundKind::UnboundedPreceding), null, FrameExclusion::CurrentRow))->render($out);
        self::assertSame('RANGE UNBOUNDED PRECEDING EXCLUDE CURRENT ROW', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAnEndAtUnboundedPreceding(): void
    {
        $this->expectExceptionMessage('A frame does not end at UNBOUNDED PRECEDING.');
        new WindowFrame(FrameMode::Rows, new FrameBound(FrameBoundKind::CurrentRow), new FrameBound(FrameBoundKind::UnboundedPreceding));
    }

    public function testRejectsAStartAfterTheCurrentRowWithoutAnEnd(): void
    {
        $this->expectExceptionMessage('A frame starting after the current row does not end at or before it.');
        new WindowFrame(FrameMode::Rows, new FrameBound(FrameBoundKind::OffsetFollowing, new Constant(new IntegerConstant('1'))));
    }
}
