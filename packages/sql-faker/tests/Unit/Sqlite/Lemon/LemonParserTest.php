<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lemon;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Lemon\LemonParser;

#[CoversClass(LemonParser::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
final class LemonParserTest extends TestCase
{
    public function testParseSimpleRule(): void
    {
        $input = <<<'LEMON'
        cmd ::= SELECT expr. { action }
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertSame('cmd', $grammar->startSymbol);
        self::assertArrayHasKey('cmd', $grammar->ruleMap);
        self::assertCount(1, $grammar->ruleMap['cmd']->alternatives);

        $alt = $grammar->ruleMap['cmd']->alternatives[0];
        self::assertCount(2, $alt->symbols);
        self::assertInstanceOf(Terminal::class, $alt->symbols[0]);
        self::assertSame('SELECT', $alt->symbols[0]->value);
        self::assertInstanceOf(NonTerminal::class, $alt->symbols[1]);
        self::assertSame('expr', $alt->symbols[1]->value);
    }

    public function testParseMultipleRules(): void
    {
        $input = <<<'LEMON'
        cmd ::= SELECT expr.
        cmd ::= INSERT INTO nm.
        expr ::= INTEGER.
        nm ::= ID.
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertSame('cmd', $grammar->startSymbol);
        self::assertCount(3, $grammar->ruleMap);
        self::assertArrayHasKey('cmd', $grammar->ruleMap);
        self::assertArrayHasKey('expr', $grammar->ruleMap);
        self::assertArrayHasKey('nm', $grammar->ruleMap);

        self::assertCount(2, $grammar->ruleMap['cmd']->alternatives);
    }

    public function testParseEmptyProduction(): void
    {
        $input = <<<'LEMON'
        opt_where ::= .
        opt_where ::= WHERE expr.
        expr ::= INTEGER.
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertCount(2, $grammar->ruleMap['opt_where']->alternatives);
        self::assertCount(0, $grammar->ruleMap['opt_where']->alternatives[0]->symbols);
    }

    public function testParseAliasesStripped(): void
    {
        $input = <<<'LEMON'
        expr(A) ::= expr(B) PLUS expr(C).
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertSame('expr', $grammar->startSymbol);
        $alt = $grammar->ruleMap['expr']->alternatives[0];
        self::assertCount(3, $alt->symbols);
        self::assertInstanceOf(NonTerminal::class, $alt->symbols[0]);
        self::assertSame('expr', $alt->symbols[0]->value());
        self::assertInstanceOf(Terminal::class, $alt->symbols[1]);
        self::assertSame('PLUS', $alt->symbols[1]->value());
        self::assertInstanceOf(NonTerminal::class, $alt->symbols[2]);
        self::assertSame('expr', $alt->symbols[2]->value());
    }

    public function testParseWithDirectives(): void
    {
        $input = <<<'LEMON'
        %left AND.
        %left OR.
        %token_type {int}
        cmd ::= SELECT expr.
        expr ::= INTEGER.
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertSame('cmd', $grammar->startSymbol);
        self::assertCount(2, $grammar->ruleMap);
    }

    public function testParseCommentsStripped(): void
    {
        $input = <<<'LEMON'
        /* This is a comment */
        cmd ::= SELECT expr. // inline comment
        expr ::= INTEGER.
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertSame('cmd', $grammar->startSymbol);
        self::assertCount(2, $grammar->ruleMap);
    }

    public function testParseThrowsOnNoRules(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No grammar rules parsed');

        (new LemonParser())->parse('%left AND.');
    }

    public function testTerminalDetection(): void
    {
        $input = <<<'LEMON'
        cmd ::= SELECT ALL expr FROM nm.
        expr ::= INTEGER.
        nm ::= ID.
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        $alt = $grammar->ruleMap['cmd']->alternatives[0];
        self::assertInstanceOf(Terminal::class, $alt->symbols[0]);
        self::assertInstanceOf(Terminal::class, $alt->symbols[1]);
        self::assertInstanceOf(NonTerminal::class, $alt->symbols[2]);
        self::assertInstanceOf(Terminal::class, $alt->symbols[3]);
        self::assertInstanceOf(NonTerminal::class, $alt->symbols[4]);
    }

    public function testParseSqliteGrammarCache(): void
    {
        $grammar = Grammar::loadFromFile(
            __DIR__ . '/../../../../resources/ast/sqlite-3.47.2.php'
        );

        self::assertSame('input', $grammar->startSymbol);
        self::assertArrayHasKey('cmd', $grammar->ruleMap);
        self::assertArrayHasKey('select', $grammar->ruleMap);
        self::assertArrayHasKey('expr', $grammar->ruleMap);
        self::assertGreaterThan(100, count($grammar->ruleMap));
    }
}
