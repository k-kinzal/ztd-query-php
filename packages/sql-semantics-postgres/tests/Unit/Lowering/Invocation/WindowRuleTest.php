<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Invocation\WindowRule;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FrameBoundKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(WindowRule::class)]
#[Small]
final class WindowRuleTest extends TestCase
{
    public function testOverLowersNoClauseToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f()');
        $node = (new WindowRule($lowering))->over($tree->find('over_clause')[0]);
        self::assertNull($node);
    }

    public function testSpecificationLowersEveryPart(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() OVER (w PARTITION BY 1, 2 GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING EXCLUDE NO OTHERS)');
        $node = (new WindowRule($lowering))->specification($tree->find('window_specification')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('(w PARTITION BY 1, 2 GROUPS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING)', (new Lexical())->join($out->pieces()));
    }

    public function testFrameLowersNoFrameToNull(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() OVER ()');
        $node = (new WindowRule($lowering))->frame($tree->find('opt_frame_clause')[0]);
        self::assertNull($node);
    }

    public function testFrameRejectsAFrameEndingBeforeItStarts(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() OVER (ROWS BETWEEN CURRENT ROW AND 1 PRECEDING)');
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('frame starting from current row cannot have preceding rows');
        (new WindowRule($lowering))->frame($tree->find('opt_frame_clause')[0]);
    }

    public function testCheckRejectsAStartAfterTheCurrentRowAlone(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('frame starting from following row cannot end with current row');
        (new WindowRule($lowering))->check(FrameBoundKind::OffsetFollowing, null);
    }

    public function testBoundLowersAnOffset(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT f() OVER (RANGE BETWEEN 3 FOLLOWING AND 4 FOLLOWING)');
        $node = (new WindowRule($lowering))->bound($tree->find('frame_bound')[0]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $out->node($node);
        self::assertSame('3 FOLLOWING', (new Lexical())->join($out->pieces()));
    }
}
