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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(JsonArgument::class)]
#[Small]
final class JsonArgumentTest extends TestCase
{
    public function testDeriveClauseDerivesTheValue(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonArgument(new JsonValueExpression($value), new Name('x')))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesTheName(): void
    {
        $argument = new JsonArgument(new JsonValueExpression(new Constant(new IntegerConstant('1'))), new Name('x'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $argument->render($out);
        self::assertSame('1 AS x', (new Lexical())->join($out->pieces()));
    }
}
