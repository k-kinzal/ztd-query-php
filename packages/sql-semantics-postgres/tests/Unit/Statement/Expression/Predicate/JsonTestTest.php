<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\Negation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonTest;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(JsonTest::class)]
#[Small]
final class JsonTestTest extends TestCase
{
    public function testDeriveScalarIsBooleanForAString(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonTest(new Constant(new StringConstant('{}')), false, JsonItemKind::JsonObject), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
    }

    public function testDeriveScalarReportsAnInteger(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonTest(new Constant(new IntegerConstant('1')), false, JsonItemKind::Json), $derivation->environment());
        self::assertInstanceOf(Invalid::class, $fact->type);
    }

    public function testRenderWritesTheKind(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new JsonTest(new NullLiteral(), true, JsonItemKind::JsonValue))->render($out);
        self::assertSame('NULL IS NOT JSON VALUE', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsANegation(): void
    {
        $this->expectExceptionMessage('The tested value needs parentheses to keep its place.');
        new JsonTest(new Negation(new NullLiteral()), false, JsonItemKind::Json);
    }
}
