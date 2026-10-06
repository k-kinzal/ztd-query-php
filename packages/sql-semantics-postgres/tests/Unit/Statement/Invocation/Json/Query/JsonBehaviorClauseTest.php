<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(JsonBehaviorClause::class)]
#[Small]
final class JsonBehaviorClauseTest extends TestCase
{
    public function testRejectsNoBehavior(): void
    {
        $this->expectExceptionMessage('A behavior clause has at least one behavior.');
        new JsonBehaviorClause(null, null);
    }

    public function testDeriveClauseDerivesBothDefaults(): void
    {
        $empty = new Constant(new IntegerConstant('1'));
        $error = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonBehaviorClause(new JsonBehavior(JsonBehaviorKind::Default, $empty), new JsonBehavior(JsonBehaviorKind::Default, $error)))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($empty));
        self::assertTrue($derivation->facts()->covers($error));
    }

    public function testRenderWritesOnEmptyThenOnError(): void
    {
        $clause = new JsonBehaviorClause(new JsonBehavior(JsonBehaviorKind::Null), new JsonBehavior(JsonBehaviorKind::Error));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $clause->render($out);
        self::assertSame('NULL ON EMPTY ERROR ON ERROR', (new Lexical())->join($out->pieces()));
    }
}
