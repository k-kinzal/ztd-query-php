<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Codec;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Codec::class)]
#[Medium]
final class CodecTest extends TestCase
{
    public function testNameIsWrittenBareWhenTheProfileAllowsIt(): void
    {
        $codec = Platforms::of('sqlite')->codec((new Semantics(Dialect::Sqlite))->profile());

        self::assertSame('users', $codec->name(new Name('users'), NameUse::Relation));
    }

    public function testNameIsQuotedWhenItWouldNotDecodeToItselfBare(): void
    {
        $codec = Platforms::of('sqlite')->codec((new Semantics(Dialect::Sqlite))->profile());

        self::assertSame('`order items`', $codec->name(new Name('order items'), NameUse::Column));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Alias));
    }

    public function testNameSpellingDecodesToTheSameNameWhenAnalyzedAgain(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $spelling = Platforms::of('sqlite')->codec($semantics->profile())->name(new Name('a`b'), NameUse::Column);

        $query = $semantics->analyze('SELECT ' . $spelling);

        self::assertSame('a`b', $query->field(0)->name?->value);
    }
}
