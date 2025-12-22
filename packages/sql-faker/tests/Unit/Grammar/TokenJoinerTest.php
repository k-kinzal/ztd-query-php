<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\TokenJoiner;

#[CoversClass(TokenJoiner::class)]
final class TokenJoinerTest extends TestCase
{
    public function testJoinEmptyArrayReturnsEmptyString(): void
    {
        self::assertSame('', TokenJoiner::join([]));
    }

    public function testJoinSingleToken(): void
    {
        self::assertSame('SELECT', TokenJoiner::join(['SELECT']));
    }

    public function testJoinMultipleTokensWithSpaces(): void
    {
        self::assertSame('SELECT a FROM b', TokenJoiner::join(['SELECT', 'a', 'FROM', 'b']));
    }

    public function testJoinIdentifierFollowedByOpenParen(): void
    {
        self::assertSame('COUNT(*)', TokenJoiner::join(['COUNT', '(', '*', ')']));
    }

    public function testJoinQuotedIdentifierFollowedByOpenParen(): void
    {
        self::assertSame('`func`(*)', TokenJoiner::join(['`func`', '(', '*', ')']));
    }

    public function testJoinDoubleQuotedIdentifierFollowedByOpenParen(): void
    {
        self::assertSame('"func"(*)', TokenJoiner::join(['"func"', '(', '*', ')']));
    }

    public function testJoinNonIdentifierBeforeOpenParen(): void
    {
        self::assertSame('1 (2)', TokenJoiner::join(['1', '(', '2', ')']));
    }

    public function testJoinCloseParenNoSpace(): void
    {
        self::assertSame('(a)', TokenJoiner::join(['(', 'a', ')']));
    }

    public function testJoinOpenParenNoSpaceAfter(): void
    {
        self::assertSame('(a)', TokenJoiner::join(['(', 'a', ')']));
    }

    public function testJoinCommaNoSpaceBefore(): void
    {
        self::assertSame('a, b', TokenJoiner::join(['a', ',', 'b']));
    }

    public function testJoinSemicolonNoSpaceBefore(): void
    {
        self::assertSame('a; b', TokenJoiner::join(['a', ';', 'b']));
    }

    public function testJoinDotNoSpace(): void
    {
        self::assertSame('a.b', TokenJoiner::join(['a', '.', 'b']));
    }

    public function testJoinPrevDotNoSpace(): void
    {
        self::assertSame('a.b.c', TokenJoiner::join(['a', '.', 'b', '.', 'c']));
    }

    public function testJoinBracketNoSpace(): void
    {
        self::assertSame('a [0]', TokenJoiner::join(['a', '[', '0', ']']));
    }

    public function testJoinOpenBracketNoSpaceAfterPrev(): void
    {
        self::assertSame('[x]', TokenJoiner::join(['[', 'x', ']']));
    }

    public function testJoinCloseBracketNoSpaceBefore(): void
    {
        self::assertSame('a [b]', TokenJoiner::join(['a', '[', 'b', ']']));
    }

    public function testJoinPrevIsBracketNoSpaceAfter(): void
    {
        self::assertSame('[x', TokenJoiner::join(['[', 'x']));
    }

    public function testJoinCloseBracketAttaches(): void
    {
        self::assertSame('x]', TokenJoiner::join(['x', ']']));
    }

    public function testJoinNoSpacePairsExactMatch(): void
    {
        self::assertSame('a::b', TokenJoiner::join(['a', '::', 'b'], [['::', '*'], ['*', '::']]));
    }

    public function testJoinNoSpacePairsWildcardPrev(): void
    {
        self::assertSame('x:: y', TokenJoiner::join(['x', '::', 'y'], [['*', '::']]));
    }

    public function testJoinNoSpacePairsWildcardToken(): void
    {
        self::assertSame('::z', TokenJoiner::join(['::', 'z'], [['::', '*']]));
    }

    public function testJoinNoSpacePairsBothDirections(): void
    {
        self::assertSame('x::y', TokenJoiner::join(['x', '::', 'y'], [['*', '::'], ['::', '*']]));
    }

    public function testJoinNoSpacePairsNoMatch(): void
    {
        self::assertSame('a + b', TokenJoiner::join(['a', '+', 'b'], [['::', '*']]));
    }

    public function testJoinTrimsOutput(): void
    {
        self::assertSame('SELECT', TokenJoiner::join(['SELECT']));
    }

    public function testIsIdentifierSimpleWord(): void
    {
        self::assertTrue(TokenJoiner::isIdentifier('abc'));
        self::assertTrue(TokenJoiner::isIdentifier('_x'));
        self::assertTrue(TokenJoiner::isIdentifier('A'));
        self::assertTrue(TokenJoiner::isIdentifier('foo123'));
        self::assertTrue(TokenJoiner::isIdentifier('_'));
    }

    public function testIsIdentifierBacktickQuoted(): void
    {
        self::assertTrue(TokenJoiner::isIdentifier('`foo`'));
        self::assertTrue(TokenJoiner::isIdentifier('`ab`'));
    }

    public function testIsIdentifierDoubleQuoted(): void
    {
        self::assertTrue(TokenJoiner::isIdentifier('"foo"'));
        self::assertTrue(TokenJoiner::isIdentifier('"ab"'));
    }

    public function testIsIdentifierRejectsNonIdentifier(): void
    {
        self::assertFalse(TokenJoiner::isIdentifier('123'));
        self::assertFalse(TokenJoiner::isIdentifier('('));
        self::assertFalse(TokenJoiner::isIdentifier(''));
        self::assertFalse(TokenJoiner::isIdentifier('+'));
        self::assertFalse(TokenJoiner::isIdentifier('1abc'));
    }

    public function testIsIdentifierRejectsSingleChar(): void
    {
        self::assertFalse(TokenJoiner::isIdentifier('`'));
        self::assertFalse(TokenJoiner::isIdentifier('"'));
    }

    public function testIsIdentifierRejectsMismatchedQuotes(): void
    {
        self::assertFalse(TokenJoiner::isIdentifier('`foo"'));
        self::assertFalse(TokenJoiner::isIdentifier('"foo`'));
    }

    public function testIsQuotedIdentifierBacktick(): void
    {
        self::assertTrue(TokenJoiner::isQuotedIdentifier('`x`'));
    }

    public function testIsQuotedIdentifierDoubleQuote(): void
    {
        self::assertTrue(TokenJoiner::isQuotedIdentifier('"x"'));
    }

    public function testIsQuotedIdentifierRejectsTooShort(): void
    {
        self::assertFalse(TokenJoiner::isQuotedIdentifier(''));
        self::assertFalse(TokenJoiner::isQuotedIdentifier('x'));
    }

    public function testIsQuotedIdentifierRejectsSingleQuote(): void
    {
        self::assertFalse(TokenJoiner::isQuotedIdentifier("'x'"));
    }

    public function testIsQuotedIdentifierRejectsMismatch(): void
    {
        self::assertFalse(TokenJoiner::isQuotedIdentifier('`x"'));
        self::assertFalse(TokenJoiner::isQuotedIdentifier('"x`'));
    }

    public function testJoinComplexSqlStatement(): void
    {
        $tokens = ['SELECT', 'COUNT', '(', '*', ')', 'FROM', 'users', 'WHERE', 'id', '=', '1'];
        self::assertSame('SELECT COUNT(*) FROM users WHERE id = 1', TokenJoiner::join($tokens));
    }

    public function testJoinWithMultipleNoSpacePairs(): void
    {
        $tokens = ['a', '::', 'b', '->', 'c'];
        $pairs = [['::', '*'], ['*', '::'], ['->', '*'], ['*', '->']];
        self::assertSame('a::b->c', TokenJoiner::join($tokens, $pairs));
    }

    public function testJoinNoSpacePairsBothWildcard(): void
    {
        self::assertSame('ab', TokenJoiner::join(['a', 'b'], [['*', '*']]));
    }

    public function testJoinNoSpacePairsExactPrevMatch(): void
    {
        self::assertSame('ab c', TokenJoiner::join(['a', 'b', 'c'], [['a', '*']]));
    }

    public function testJoinNoSpacePairsBreakOnFirstMatch(): void
    {
        $tokens = ['a', '::', 'b'];
        $pairs = [['::', '*'], ['x', 'y']];
        $result = TokenJoiner::join($tokens, $pairs);
        self::assertSame('a ::b', TokenJoiner::join(['a', '::', 'b'], [['::', '*']]));
        self::assertSame('a ::b', TokenJoiner::join(['a', '::', 'b'], [['::', '*'], ['unused', 'pair']]));
    }

    public function testJoinNoTrimmingNeeded(): void
    {
        self::assertSame('a', TokenJoiner::join(['a']));
        $result = TokenJoiner::join(['SELECT', 'a']);
        self::assertSame('SELECT a', $result);
    }

    public function testIsIdentifierRejectsTrailingSpecialChar(): void
    {
        self::assertFalse(TokenJoiner::isIdentifier('abc+'));
        self::assertFalse(TokenJoiner::isIdentifier('abc.'));
        self::assertFalse(TokenJoiner::isIdentifier('foo bar'));
    }

    public function testIsQuotedIdentifierMinimalTwoCharQuote(): void
    {
        self::assertTrue(TokenJoiner::isQuotedIdentifier('``'));
        self::assertTrue(TokenJoiner::isQuotedIdentifier('""'));
    }

    public function testIsQuotedIdentifierRejectsThreeCharMismatch(): void
    {
        self::assertFalse(TokenJoiner::isQuotedIdentifier('`a"'));
    }
}
