<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Sqlite\Lemon\LemonParser;

#[CoversClass(LemonParser::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
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

    public function testParseExpandsInlineTokenAlternatives(): void
    {
        $input = <<<'LEMON'
        likeop(A) ::= LIKE_KW|MATCH(A).
        expr(A) ::= expr(A) STAR|SLASH|REM(OP) expr(Y).
        LEMON;

        $grammar = (new LemonParser())->parse($input);

        self::assertSame(
            [['LIKE_KW'], ['MATCH']],
            array_map(
                static fn (Production $production): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $production->symbols,
                ),
                $grammar->ruleMap['likeop']->alternatives,
            ),
        );
        self::assertSame(
            [
                ['expr', 'STAR', 'expr'],
                ['expr', 'SLASH', 'expr'],
                ['expr', 'REM', 'expr'],
            ],
            array_map(
                static fn (Production $production): array => array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $production->symbols,
                ),
                $grammar->ruleMap['expr']->alternatives,
            ),
        );
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
        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage('No grammar rules parsed from the Lemon grammar.');

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

    public function testParseFileReadsAGrammarFromDisk(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lemon-');
        self::assertIsString($path);
        file_put_contents($path, "cmd ::= SELECT.\n");

        self::assertSame('cmd', (new LemonParser())->parseFile($path)->startSymbol);

        unlink($path);
    }

    public function testParseFileReportsAFileItCannotRead(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read: ');

        (new LemonParser())->parseFile(sys_get_temp_dir() . '/no-such-lemon-grammar.y');
    }
}
