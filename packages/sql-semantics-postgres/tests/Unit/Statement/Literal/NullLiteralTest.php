<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;

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
