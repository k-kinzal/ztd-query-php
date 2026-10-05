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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(FrameBound::class)]
#[Small]
final class FrameBoundTest extends TestCase
{
    public function testDeriveClauseDerivesTheOffset(): void
    {
        $scalar = new Constant(new IntegerConstant('3'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new FrameBound(FrameBoundKind::OffsetFollowing, $scalar))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($scalar));
    }

    public function testRenderWritesTheOffsetBeforeTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new FrameBound(FrameBoundKind::OffsetPreceding, new Constant(new IntegerConstant('2'))))->render($out);
        self::assertSame('2 PRECEDING', (new Lexical())->join($out->pieces()));
        $second = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new FrameBound(FrameBoundKind::UnboundedFollowing))->render($second);
        self::assertSame('UNBOUNDED FOLLOWING', (new Lexical())->join($second->pieces()));
    }

    public function testRejectsAnOffsetBoundWithoutAnOffset(): void
    {
        $this->expectExceptionMessage('A frame bound has an offset exactly when it is an offset bound.');
        new FrameBound(FrameBoundKind::OffsetPreceding);
    }

    public function testRenderQuotesABareUnboundedColumn(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT count(*) OVER (ORDER BY 1 ROWS "unbounded" PRECEDING) FROM (SELECT 1 AS unbounded) AS s', []);
        self::assertSame('SELECT count(*) OVER (ORDER BY 1 ROWS "unbounded" PRECEDING) FROM (SELECT 1 AS unbounded) AS s', $query->toString());
    }
}
