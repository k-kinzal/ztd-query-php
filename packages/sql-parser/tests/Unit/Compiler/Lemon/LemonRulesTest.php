<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonRules;
use SqlParser\Compiler\Lemon\LemonScanner;
use SqlParser\Compiler\Lemon\LemonToken;
use SqlParser\Compiler\Lemon\LemonTokens;
use SqlParser\Compiler\SourceReader;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(LemonRules::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(LemonScanner::class)]
#[UsesClass(LemonToken::class)]
#[UsesClass(LemonTokens::class)]
#[UsesClass(SourceReader::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(\SqlParser\Grammar\Precedence::class)]
#[Small]
final class LemonRulesTest extends TestCase
{
    public function testReadRecordsARuleWithAliasesPrecedenceAndCode(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('expr(A) ::= MINUS expr(X). [BITNOT] { A = X; } cmd ::= .'));
        $builder = new GrammarBuilder();
        $builder->precedence(['BITNOT'], \SqlParser\Grammar\Associativity::Right);
        $rules = new LemonRules();
        $rules->read($tokens, $builder);
        $rules->read($tokens, $builder);
        $grammar = $builder->build();

        self::assertSame([$grammar->symbols->id('MINUS'), $grammar->symbols->id('expr')], $grammar->rules[1]->rhs);
        self::assertSame($grammar->symbols->id('BITNOT'), $grammar->rules[1]->precedenceSymbol);
        self::assertSame([], $grammar->rules[2]->rhs);
        self::assertTrue($tokens->atEnd());
    }

    public function testReadRejectsAMissingArrow(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('expr MINUS.'));

        $this->expectException(GrammarSourceException::class);

        (new LemonRules())->read($tokens, new GrammarBuilder());
    }

    public function testReadRejectsAnUnterminatedRule(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('expr ::= MINUS'));

        $this->expectException(GrammarSourceException::class);

        (new LemonRules())->read($tokens, new GrammarBuilder());
    }

    public function testReadRejectsCodeInsideTheRightHandSide(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('expr ::= { x } MINUS.'));

        $this->expectException(GrammarSourceException::class);

        (new LemonRules())->read($tokens, new GrammarBuilder());
    }

    public function testSymbolGathersAnAlternationIntoATokenClass(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('|INDEXED|JOIN_KW rest'));
        $builder = new GrammarBuilder();

        self::assertSame('ID|INDEXED|JOIN_KW', (new LemonRules())->symbol('ID', $tokens, $builder));
        self::assertTrue($builder->isTerminal('ID|INDEXED|JOIN_KW'));
        self::assertTrue($builder->isTerminal('JOIN_KW'));
        self::assertSame('rest', $tokens->peek()?->text);
    }

    public function testSymbolDeclaresALoneTerminal(): void
    {
        $builder = new GrammarBuilder();

        self::assertSame('SELECT', (new LemonRules())->symbol('SELECT', new LemonTokens([]), $builder));
        self::assertTrue($builder->isTerminal('SELECT'));
        self::assertSame('nm', (new LemonRules())->symbol('nm', new LemonTokens([]), $builder));
        self::assertFalse($builder->isTerminal('nm'));
    }

    public function testSymbolRejectsANonterminalInAnAlternation(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('|nm'));

        $this->expectException(GrammarSourceException::class);

        (new LemonRules())->symbol('ID', $tokens, new GrammarBuilder());
    }

    public function testSkipAlias(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('(A) rest'));
        (new LemonRules())->skipAlias($tokens);

        self::assertSame('rest', $tokens->peek()?->text);
        (new LemonRules())->skipAlias($tokens);
        self::assertSame('rest', $tokens->peek()?->text);
    }

    public function testIsTerminalName(): void
    {
        self::assertTrue(LemonRules::isTerminalName('SELECT'));
        self::assertTrue(LemonRules::isTerminalName('Id'));
        self::assertFalse(LemonRules::isTerminalName('expr'));
        self::assertFalse(LemonRules::isTerminalName('_x'));
    }
}
