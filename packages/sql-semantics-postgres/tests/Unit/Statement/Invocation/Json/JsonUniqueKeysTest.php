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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(JsonUniqueKeys::class)]
#[Small]
final class JsonUniqueKeysTest extends TestCase
{
    public function testDeriveClauseDerivesNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonUniqueKeys(true))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheClauseWithKeys(): void
    {
        $unique = new JsonUniqueKeys(false);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $unique->render($out);
        self::assertSame('WITHOUT UNIQUE KEYS', (new Lexical())->join($out->pieces()));
    }
}
