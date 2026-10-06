<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonValueExpression::class)]
#[Small]
final class JsonValueExpressionTest extends TestCase
{
    public function testDeriveValueAnswersTheFactsOfTheValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = (new JsonValueExpression(new Constant(new IntegerConstant('1'))))->deriveValue($derivation, $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull), $fact);
    }

    public function testDeriveValueReportsAnEncodingOnText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonValueExpression(new Constant(new StringConstant('x')), new JsonFormat(new Name('utf8'))))->deriveValue($derivation, $derivation->environment(), true);
        self::assertSame('cannot use JSON FORMAT ENCODING clause for non-bytea input types', $derivation->facts()->diagnostics[0]->message());
    }

    public function testDeriveClauseDerivesTheValue(): void
    {
        $value = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonValueExpression($value))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($value));
    }

    public function testRenderWritesTheFormat(): void
    {
        $value = new JsonValueExpression(new Constant(new StringConstant('{}')), new JsonFormat());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $value->render($out);
        self::assertSame('\'{}\' FORMAT JSON', (new Lexical())->join($out->pieces()));
    }
}
