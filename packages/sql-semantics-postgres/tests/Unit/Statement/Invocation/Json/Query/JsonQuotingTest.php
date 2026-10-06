<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQuoting;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(JsonQuoting::class)]
#[Small]
final class JsonQuotingTest extends TestCase
{
    public function testDeriveClauseDerivesNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonQuoting(true))->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderWritesTheQuotes(): void
    {
        $quoting = new JsonQuoting(true);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $quoting->render($out);
        self::assertSame('KEEP QUOTES', (new Lexical())->join($out->pieces()));
    }
}
