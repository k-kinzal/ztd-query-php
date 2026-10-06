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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonArrayConstructor::class)]
#[Small]
final class JsonArrayConstructorTest extends TestCase
{
    public function testRejectsANullClauseWithoutValues(): void
    {
        $this->expectExceptionMessage('JSON_ARRAY without values takes no NULL clause.');
        new JsonArrayConstructor([], JsonNullHandling::Absent);
    }

    public function testOutputNameIsJsonArray(): void
    {
        self::assertSame('json_array', (new JsonArrayConstructor([]))->outputName()->value);
    }

    public function testDeriveScalarIsJson(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonArrayConstructor([new JsonValueExpression(new Constant(new IntegerConstant('1')))]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Json), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheNullClause(): void
    {
        $array = new JsonArrayConstructor([new JsonValueExpression(new Constant(new IntegerConstant('1'))), new JsonValueExpression(new NullLiteral())], JsonNullHandling::Keep);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $array->render($out);
        self::assertSame('JSON_ARRAY(1, NULL NULL ON NULL)', (new Lexical())->join($out->pieces()));
    }
}
