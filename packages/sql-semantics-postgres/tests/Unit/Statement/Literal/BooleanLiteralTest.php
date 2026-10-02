<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BooleanLiteral::class)]
#[Small]
final class BooleanLiteralTest extends TestCase
{
    public function testOutputNameIsTheCatalogNameOfBoolean(): void
    {
        self::assertSame('bool', (new BooleanLiteral(true))->outputName()->value);
    }

    public function testDeriveScalarIsBooleanAndNeverNull(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new BooleanLiteral(false), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new BooleanLiteral(false))->render($out);
        self::assertSame('FALSE', (new Lexical())->join($out->pieces()));
    }
}
