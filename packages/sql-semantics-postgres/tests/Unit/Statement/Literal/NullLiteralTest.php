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
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(NullLiteral::class)]
#[Small]
final class NullLiteralTest extends TestCase
{
    public function testOutputNameIsNone(): void
    {
        self::assertNull((new NullLiteral())->outputName());
    }

    public function testDeriveScalarIsTheNullOnlyType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new NullLiteral(), $derivation->environment());
        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new NullLiteral())->render($out);
        self::assertSame('NULL', (new Lexical())->join($out->pieces()));
    }
}
