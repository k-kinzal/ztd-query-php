<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(Grammar::class)]
#[UsesClass(GrammarException::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class GrammarTest extends TestCase
{
    public function testRulesOf(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+'], ['$accept', 'expr']);
        $grammar = new Grammar($symbols, [new Rule(0, 3, [4, 0], 0), new Rule(1, 4, [4, 2, 4], 0), new Rule(2, 4, [1], 1)]);

        self::assertSame([1, 2], $grammar->rulesOf(4));
        self::assertSame([], $grammar->rulesOf(9));
    }

    public function testPrecedenceOf(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+'], ['$accept', 'expr']);
        $plus = new Precedence(1, Associativity::Left);
        $grammar = new Grammar($symbols, [new Rule(0, 3, [4, 0], 0)], [2 => $plus]);

        self::assertSame($plus, $grammar->precedenceOf(2));
        self::assertNull($grammar->precedenceOf(1));
    }

    public function testRulePrecedenceTakesTheLastTerminalUnderBisonsPolicy(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+', '*'], ['$accept', 'expr']);
        $plus = new Precedence(1, Associativity::Left);
        $grammar = new Grammar($symbols, [new Rule(0, 4, [5, 0], 0), new Rule(1, 5, [5, 2, 5, 3], 0), new Rule(2, 5, [3, 5, 2], 1)], [2 => $plus]);

        self::assertNull($grammar->rulePrecedence($grammar->rules[1]));
        self::assertSame($plus, $grammar->rulePrecedence($grammar->rules[2]));
    }

    public function testRulePrecedenceTakesTheFirstRankedTerminalUnderLemonsPolicy(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+', '*'], ['$accept', 'expr']);
        $plus = new Precedence(1, Associativity::Left);
        $grammar = new Grammar($symbols, [new Rule(0, 4, [5, 0], 0), new Rule(1, 5, [5, 2, 5, 3], 0)], [2 => $plus], PrecedencePolicy::FirstRankedTerminal);

        self::assertSame($plus, $grammar->rulePrecedence($grammar->rules[1]));
    }

    public function testRulePrecedencePrefersTheNamedTerminal(): void
    {
        $symbols = new SymbolTable(['$end', 'NUM', '+', 'NEG'], ['$accept', 'expr']);
        $neg = new Precedence(2, Associativity::Right);
        $grammar = new Grammar($symbols, [new Rule(0, 4, [5, 0], 0), new Rule(1, 5, [2, 5], 0, false, 3)], [2 => new Precedence(1, Associativity::Left), 3 => $neg]);

        self::assertSame($neg, $grammar->rulePrecedence($grammar->rules[1]));
    }

    public function testMemberPrecedenceLooksThroughATokenClass(): void
    {
        $symbols = new SymbolTable(['$end', 'ID', 'KW', 'class'], ['$accept', 'expr']);
        $kw = new Precedence(1, Associativity::Left);
        $grammar = new Grammar($symbols, [new Rule(0, 4, [5, 0], 0)], [2 => $kw], PrecedencePolicy::FirstRankedTerminal, null, [3 => [1, 2]]);

        self::assertSame($kw, $grammar->memberPrecedence(3));
        self::assertNull($grammar->memberPrecedence(1));
    }

    public function testStartSymbol(): void
    {
        $symbols = new SymbolTable(['$end'], ['$accept', 'stmt']);

        self::assertSame(2, (new Grammar($symbols, [new Rule(0, 1, [2, 0], 0)]))->startSymbol());
    }

    public function testRulesMustBeNumberedInOrder(): void
    {
        $this->expectException(GrammarException::class);

        new Grammar(new SymbolTable(['$end'], ['$accept']), [new Rule(1, 1, [], 0)]);
    }
}
