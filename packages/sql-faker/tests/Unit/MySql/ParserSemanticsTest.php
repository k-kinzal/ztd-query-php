<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\ParserSemantics;

#[CoversClass(ParserSemantics::class)]
final class ParserSemanticsTest extends TestCase
{
    public function testAppliedDropsTheQualificationInFrontOfAUserVariable(): void
    {
        self::assertSame(
            ['IDENT', '@', 'IDENT'],
            (new ParserSemantics())->applied(['IDENT', '.', 'IDENT', '@', 'IDENT']),
        );
    }

    public function testAppliedDropsTheEmptyCallAfterCurrentUser(): void
    {
        self::assertSame(
            ['CURRENT_USER', ':'],
            (new ParserSemantics())->applied(['CURRENT_USER', '(', ')', ':']),
        );
    }

    public function testAppliedGivesAnAlterEventTheClauseTheParserRequires(): void
    {
        self::assertSame(
            ['ALTER_SYM', 'EVENT_SYM', 'IDENT', 'ENABLE_SYM'],
            (new ParserSemantics())->applied(['ALTER_SYM', 'EVENT_SYM', 'IDENT']),
        );
    }

    public function testAppliedSpellsEqualsAsTheParserDoesInFrontOfAQuantifier(): void
    {
        self::assertSame(['EQ', 'ALL'], (new ParserSemantics())->applied(['EQUAL_SYM', 'ALL']));
    }

    public function testAppliedDropsAReleaseThatFollowsAChainWithNoNoBeforeIt(): void
    {
        self::assertSame(['CHAIN_SYM'], (new ParserSemantics())->applied(['CHAIN_SYM', 'RELEASE_SYM']));
    }

    public function testAppliedKeepsAReleaseThatFollowsANoChain(): void
    {
        self::assertSame(
            ['NO_SYM', 'CHAIN_SYM', 'RELEASE_SYM'],
            (new ParserSemantics())->applied(['NO_SYM', 'CHAIN_SYM', 'RELEASE_SYM']),
        );
    }

    public function testAppliedSpellsANumberAsTheParserDoesAfterSystem(): void
    {
        self::assertSame(['SYSTEM', 'NUM'], (new ParserSemantics())->applied(['SYSTEM', 'DECIMAL_NUM']));
    }
}
