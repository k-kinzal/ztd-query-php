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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonScalar;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonScalar::class)]
#[Small]
final class JsonScalarTest extends TestCase
{
    public function testOutputNameIsJsonScalar(): void
    {
        self::assertSame('json_scalar', (new JsonScalar(new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarFollowsTheValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonScalar(new Constant(new IntegerConstant('1'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Json), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheValue(): void
    {
        $scalar = new JsonScalar(new Constant(new IntegerConstant('1')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $scalar->render($out);
        self::assertSame('JSON_SCALAR(1)', (new Lexical())->join($out->pieces()));
    }
}
