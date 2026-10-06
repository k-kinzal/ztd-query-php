<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Codec::class)]
#[Small]
final class CodecTest extends TestCase
{
    public function testNameWritesAPlainAsciiWordBare(): void
    {
        $codec = new Codec(GrammarRelease::MySql8044);

        self::assertSame('users', $codec->name(new Name('users'), NameUse::Relation));
        self::assertSame('Order_Items2', $codec->name(new Name('Order_Items2'), NameUse::Column));
        self::assertSame('a', $codec->name(new Name('a'), NameUse::Alias));
    }

    public function testNameQuotesKeywordsFunctionNamesAndIntroducerLikeWords(): void
    {
        $codec = new Codec(GrammarRelease::MySql8044);

        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Column));
        self::assertSame('`ORDER`', $codec->name(new Name('ORDER'), NameUse::Relation));
        self::assertSame('`count`', $codec->name(new Name('count'), NameUse::Routine));
        self::assertSame('`action`', $codec->name(new Name('action'), NameUse::Column));
        self::assertSame('`_x`', $codec->name(new Name('_x'), NameUse::Column));
        self::assertSame('`_utf8mb4`', $codec->name(new Name('_utf8mb4'), NameUse::Column));
    }

    public function testNameQuotesWhatIsNotABareWordAndDoublesBackticks(): void
    {
        $codec = new Codec(GrammarRelease::MySql8044);

        self::assertSame('`order items`', $codec->name(new Name('order items'), NameUse::Relation));
        self::assertSame('`a``b`', $codec->name(new Name('a`b'), NameUse::Column));
        self::assertSame('`1a`', $codec->name(new Name('1a'), NameUse::Column));
        self::assertSame('`123`', $codec->name(new Name('123'), NameUse::Column));
        self::assertSame('`a$b`', $codec->name(new Name('a$b'), NameUse::Column));
        self::assertSame('`é`', $codec->name(new Name('é'), NameUse::Column));
        self::assertSame('`a"b`', $codec->name(new Name('a"b'), NameUse::Column));
        self::assertSame('``', $codec->name(new Name(''), NameUse::Column));
    }

    public function testNameFollowsTheKeywordsOfTheRelease(): void
    {
        self::assertSame('rank', (new Codec(GrammarRelease::MySql5744))->name(new Name('rank'), NameUse::Column));
        self::assertSame('`rank`', (new Codec(GrammarRelease::MySql8044))->name(new Name('rank'), NameUse::Column));
        self::assertSame('`rank`', (new Codec(GrammarRelease::MySql910))->name(new Name('rank'), NameUse::Column));
        self::assertSame('cume_dist', (new Codec(GrammarRelease::MySql5744))->name(new Name('cume_dist'), NameUse::Routine));
        self::assertSame('`cume_dist`', (new Codec(GrammarRelease::MySql8044))->name(new Name('cume_dist'), NameUse::Routine));
        self::assertSame('lateral', (new Codec(GrammarRelease::MySql5651))->name(new Name('lateral'), NameUse::Alias));
        self::assertSame('`lateral`', (new Codec(GrammarRelease::MySql847))->name(new Name('lateral'), NameUse::Alias));
        self::assertSame('json_table', (new Codec(GrammarRelease::MySql5744))->name(new Name('json_table'), NameUse::Relation));
        self::assertSame('`json_table`', (new Codec(GrammarRelease::MySql8044))->name(new Name('json_table'), NameUse::Relation));
        self::assertSame('qualify', (new Codec(GrammarRelease::MySql8044))->name(new Name('qualify'), NameUse::Column));
        self::assertSame('`qualify`', (new Codec(GrammarRelease::MySql910))->name(new Name('qualify'), NameUse::Column));
    }

    public function testNameSpellsTheSameAtEveryPosition(): void
    {
        $codec = new Codec(GrammarRelease::MySql8044);

        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Column));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Relation));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Qualifier));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Alias));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Routine));
        self::assertSame('`select`', $codec->name(new Name('select'), NameUse::Label));
        self::assertSame('users', $codec->name(new Name('users'), NameUse::Column));
        self::assertSame('users', $codec->name(new Name('users'), NameUse::Relation));
        self::assertSame('users', $codec->name(new Name('users'), NameUse::Qualifier));
        self::assertSame('users', $codec->name(new Name('users'), NameUse::Alias));
        self::assertSame('users', $codec->name(new Name('users'), NameUse::Routine));
        self::assertSame('users', $codec->name(new Name('users'), NameUse::Label));
    }
}
