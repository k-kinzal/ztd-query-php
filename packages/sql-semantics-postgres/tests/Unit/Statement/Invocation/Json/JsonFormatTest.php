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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(JsonFormat::class)]
#[Small]
final class JsonFormatTest extends TestCase
{
    public function testRejectsAnUnknownEncoding(): void
    {
        $this->expectExceptionMessage('A JSON encoding is UTF8, UTF16 or UTF32.');
        new JsonFormat(new Name('latin1'));
    }

    public function testEncodingAnswersTheEncodingWritten(): void
    {
        self::assertSame([JsonEncoding::Utf8, null], [(new JsonFormat(new Name('UTF8')))->encoding(), (new JsonFormat())->encoding()]);
    }

    public function testDeriveClauseDerivesNothing(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonFormat())->deriveClause($derivation, $derivation->environment());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderKeepsTheEncodingAsWritten(): void
    {
        $format = new JsonFormat(new Name('UTF16'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $format->render($out);
        self::assertSame('FORMAT JSON ENCODING "UTF16"', (new Lexical())->join($out->pieces()));
    }
}
