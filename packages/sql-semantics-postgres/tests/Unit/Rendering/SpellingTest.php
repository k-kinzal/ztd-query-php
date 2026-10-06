<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Spelling::class)]
#[Small]
final class SpellingTest extends TestCase
{
    public function testStringDoublesQuotesAndKeepsBackslashes(): void
    {
        self::assertSame("'a''b\\n'", (new Spelling())->string("a'b\\n"));
    }

    public function testDottedWritesLaterPartsAsLabels(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Spelling())->dotted($out, [new Name('between'), new Name('select')]);
        self::assertSame('"between".select', (new Lexical())->join($out->pieces()));
    }

    public function testDottedWritesASinglePartForItsPosition(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Spelling())->dotted($out, [new Name('left')], NameUse::Routine);
        self::assertSame('left', (new Lexical())->join($out->pieces()));
    }

    public function testColumnsWritesEveryPartAsAColumnIdentifier(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Spelling())->columns($out, [new Name('a'), new Name('select')]);
        self::assertSame('a."select"', (new Lexical())->join($out->pieces()));
    }

    public function testQualifiedWritesCatalogSchemaAndName(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Spelling())->qualified($out, new QualifiedName(new Name('T'), new Name('s'), new Name('c')));
        self::assertSame('c.s."T"', (new Lexical())->join($out->pieces()));
    }
}
