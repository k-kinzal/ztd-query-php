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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(JsonBehavior::class)]
#[Small]
final class JsonBehaviorTest extends TestCase
{
    public function testRejectsDefaultWithoutExpression(): void
    {
        $this->expectExceptionMessage('A JSON behavior has an expression exactly when it is DEFAULT.');
        new JsonBehavior(JsonBehaviorKind::Default);
    }

    public function testDeriveClauseDerivesTheDefault(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonBehavior(JsonBehaviorKind::Default, $value))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesTheKeywords(): void
    {
        $behavior = new JsonBehavior(JsonBehaviorKind::EmptyObject);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $behavior->render($out);
        self::assertSame('EMPTY OBJECT', (new Lexical())->join($out->pieces()));
    }
}
