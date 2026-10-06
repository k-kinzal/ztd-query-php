<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\CollationFor;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(CollationFor::class)]
#[Small]
final class CollationForTest extends TestCase
{
    public function testOutputNameIsTheFunctionCalled(): void
    {
        self::assertSame('pg_collation_for', (new CollationFor(new NullLiteral()))->outputName()->value);
    }

    public function testDeriveScalarIsNullableText(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new CollationFor(new Constant(new StringConstant('x'))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheKeywords(): void
    {
        $collation = new CollationFor(new Constant(new StringConstant('x')));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $collation->render($out);
        self::assertSame('COLLATION FOR (\'x\')', (new Lexical())->join($out->pieces()));
    }
}
