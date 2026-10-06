<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonTest;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonParse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonParse::class)]
#[Small]
final class JsonParseTest extends TestCase
{
    public function testOutputNameIsJson(): void
    {
        self::assertSame('json', (new JsonParse(new JsonValueExpression(new NullLiteral())))->outputName()->value);
    }

    public function testDeriveScalarFollowsTheValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonParse(new JsonValueExpression(new NullLiteral())), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Json), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheUniqueness(): void
    {
        $parse = new JsonParse(new JsonValueExpression(new Constant(new StringConstant('{}'))), new JsonUniqueKeys(true));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $parse->render($out);
        self::assertSame('JSON(\'{}\' WITH UNIQUE KEYS)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAValueThatWouldTakeTheUniqueness(): void
    {
        $this->expectExceptionMessage('A value ending in IS JSON without a uniqueness clause would take the uniqueness clause; group it.');
        new JsonParse(new JsonValueExpression(new JsonTest(new NullLiteral(), false, JsonItemKind::Json)), new JsonUniqueKeys(true));
    }
}
