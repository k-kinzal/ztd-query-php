<?php

declare(strict_types=1);

namespace Tests\Unit\Automaton;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\Bitset;

#[CoversClass(Bitset::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class BitsetTest extends TestCase
{
    public function testEmpty(): void
    {
        self::assertSame([0, 0], Bitset::empty(33));
        self::assertSame([0], Bitset::empty(32));
        self::assertSame([], Bitset::empty(0));
    }

    public function testAdd(): void
    {
        $set = Bitset::empty(64);
        Bitset::add($set, 3);
        Bitset::add($set, 40);

        self::assertSame([8, 256], $set);
    }

    public function testHas(): void
    {
        $set = Bitset::empty(64);
        Bitset::add($set, 40);

        self::assertTrue(Bitset::has($set, 40));
        self::assertFalse(Bitset::has($set, 41));
    }

    public function testUnion(): void
    {
        $into = Bitset::empty(64);
        $from = Bitset::empty(64);
        Bitset::add($into, 1);
        Bitset::add($from, 33);
        Bitset::union($into, $from);

        self::assertSame([1, 33], Bitset::members($into));
    }

    public function testMembers(): void
    {
        $set = Bitset::empty(70);
        Bitset::add($set, 0);
        Bitset::add($set, 31);
        Bitset::add($set, 32);
        Bitset::add($set, 69);

        self::assertSame([0, 31, 32, 69], Bitset::members($set));
        self::assertSame([], Bitset::members(Bitset::empty(8)));
    }
}
