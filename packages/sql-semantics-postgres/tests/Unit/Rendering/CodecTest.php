<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Codec::class)]
#[Small]
final class CodecTest extends TestCase
{
    public function testNameWritesAPlainLowerCaseWordBare(): void
    {
        self::assertSame('order_items$1', (new Codec(GrammarRelease::PostgreSql172))->name(new Name('order_items$1'), NameUse::Relation));
    }

    public function testNameQuotesWhatWouldNotDecodeToTheSameName(): void
    {
        $codec = new Codec(GrammarRelease::PostgreSql172);
        self::assertSame('"Foo"', $codec->name(new Name('Foo'), NameUse::Column));
        self::assertSame('"a""b"', $codec->name(new Name('a"b'), NameUse::Column));
        self::assertSame('"1a"', $codec->name(new Name('1a'), NameUse::Column));
        self::assertSame('"é"', $codec->name(new Name('é'), NameUse::Column));
    }

    public function testNameQuotesAKeywordThePositionDoesNotAccept(): void
    {
        $codec = new Codec(GrammarRelease::PostgreSql172);
        self::assertSame('"select"', $codec->name(new Name('select'), NameUse::Column));
        self::assertSame('select', $codec->name(new Name('select'), NameUse::Label));
        self::assertSame('"integer"', $codec->name(new Name('integer'), NameUse::Routine));
        self::assertSame('json_table', (new Codec(GrammarRelease::PostgreSql166))->name(new Name('json_table'), NameUse::Routine));
        self::assertSame('"json_table"', $codec->name(new Name('json_table'), NameUse::Routine));
    }

    public function testNameRejectsANameTheServerCannotStore(): void
    {
        $this->expectExceptionMessage('A PostgreSQL name holds 1 to 63 bytes and no zero byte.');
        (new Codec(GrammarRelease::PostgreSql172))->name(new Name(''), NameUse::Column);
    }
}
