<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ReturnStatement;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(ReturnStatement::class)]
#[Small]
final class ReturnStatementTest extends TestCase
{
    public function testDeriveClauseDerivesTheValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $value = new BooleanLiteral(true);
        (new ReturnStatement($value))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesReturn(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ReturnStatement(new BooleanLiteral(false)))->render($out);
        self::assertSame('RETURN FALSE', (new Lexical())->join($out->pieces()));
    }
}
