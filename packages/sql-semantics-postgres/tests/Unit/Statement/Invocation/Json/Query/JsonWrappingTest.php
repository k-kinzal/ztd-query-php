<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapping;

#[CoversClass(JsonWrapping::class)]
#[Small]
final class JsonWrappingTest extends TestCase
{
    public function testDeriveClauseDerivesNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonWrapping(JsonWrapperKind::Without))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheWrapper(): void
    {
        $wrapping = new JsonWrapping(JsonWrapperKind::Conditional);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $wrapping->render($out);
        self::assertSame('WITH CONDITIONAL WRAPPER', (new Lexical())->join($out->pieces()));
    }
}
