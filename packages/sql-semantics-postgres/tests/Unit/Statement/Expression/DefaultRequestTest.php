<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DefaultRequest::class)]
#[Small]
final class DefaultRequestTest extends TestCase
{
    public function testDeriveScalarIsUnknownUntilAssigned(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new DefaultRequest(), $derivation->environment());
        self::assertEquals(new Known(Builtin::Unknown), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheKeyword(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new DefaultRequest())->render($out);
        self::assertSame('DEFAULT', (new Lexical())->join($out->pieces()));
    }
}
