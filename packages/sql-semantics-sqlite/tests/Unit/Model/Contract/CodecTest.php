<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Contract\Codec;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Codec::class)]
#[Medium]
final class CodecTest extends TestCase
{
    public function testNameIsWrittenBareWhenTheProfileAllowsIt(): void
    {
        $codec = Platform::shared()->codec((new Inspection())->profile());

        self::assertSame('users', $codec->name(new Name('users'), NameUse::Relation));
    }

    public function testNameIsQuotedWhenItWouldNotDecodeToItselfBare(): void
    {
        $codec = Platform::shared()->codec((new Inspection())->profile());

        self::assertSame('`order items`', $codec->name(new Name('order items'), NameUse::Column));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Alias));
    }

    public function testNameSpellingDecodesToTheSameNameWhenAnalyzedAgain(): void
    {
        $semantics = new Inspection();
        $spelling = Platform::shared()->codec($semantics->profile())->name(new Name('a`b'), NameUse::Column);

        $query = $semantics->diagnose('SELECT ' . $spelling);

        self::assertSame('a`b', $query->field(0)->name?->value);
    }
}
