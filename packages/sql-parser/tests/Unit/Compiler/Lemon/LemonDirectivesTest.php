<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonDirectives;
use SqlParser\Compiler\Lemon\LemonScanner;
use SqlParser\Compiler\Lemon\LemonToken;
use SqlParser\Compiler\Lemon\LemonTokens;
use SqlParser\Compiler\SourceReader;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(LemonDirectives::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(LemonScanner::class)]
#[UsesClass(LemonToken::class)]
#[UsesClass(LemonTokens::class)]
#[UsesClass(SourceReader::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[Small]
final class LemonDirectivesTest extends TestCase
{
    public function testReadRecordsTokensPrecedenceFallbackWildcardClassesAndStart(): void
    {
        $scanner = new LemonScanner();
        $builder = new GrammarBuilder();
        $directives = new LemonDirectives();
        $directives->read('token', new LemonTokens($scanner->scan('A B.')), $builder);
        $directives->read('left', new LemonTokens($scanner->scan('PLUS MINUS.')), $builder);
        $directives->read('right', new LemonTokens($scanner->scan('NOT.')), $builder);
        $directives->read('nonassoc', new LemonTokens($scanner->scan('ON.')), $builder);
        $directives->read('fallback', new LemonTokens($scanner->scan('ID A B.')), $builder);
        $directives->read('wildcard', new LemonTokens($scanner->scan('ANY.')), $builder);
        $directives->read('token_class', new LemonTokens($scanner->scan('id ID|INDEXED.')), $builder);
        $directives->read('start_symbol', new LemonTokens($scanner->scan('s')), $builder);
        $directives->read('name', new LemonTokens($scanner->scan('parser')), $builder);
        $directives->read('type', new LemonTokens($scanner->scan('nm {Token}')), $builder);
        $directives->read('include', new LemonTokens($scanner->scan('{ int x; }')), $builder);
        $builder->rule('s', ['id', 'PLUS']);
        $grammar = $builder->build();
        $symbols = $grammar->symbols;

        self::assertSame(1, $grammar->precedenceOf($symbols->id('MINUS') ?? -1)?->level);
        self::assertSame(Associativity::Right, $grammar->precedenceOf($symbols->id('NOT') ?? -1)?->associativity);
        self::assertSame(Associativity::NonAssoc, $grammar->precedenceOf($symbols->id('ON') ?? -1)?->associativity);
        self::assertSame($symbols->id('ID'), $grammar->fallbacks[$symbols->id('A') ?? -1]);
        self::assertSame($symbols->id('ANY'), $grammar->wildcard);
        self::assertSame([$symbols->id('ID'), $symbols->id('INDEXED')], $grammar->tokenClasses[$symbols->id('id') ?? -1]);
        self::assertSame('s', $symbols->name($grammar->startSymbol()));
    }

    public function testReadRejectsAFallbackWithoutATarget(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('.'));

        $this->expectException(GrammarSourceException::class);

        (new LemonDirectives())->read('fallback', $tokens, new GrammarBuilder());
    }

    public function testSkip(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('nm {Token} rest'));
        $directives = new LemonDirectives();
        $directives->skip('type', $tokens);

        self::assertSame('rest', $tokens->peek()?->text);
        $directives->skip('stack_size', $tokens);
        self::assertTrue($tokens->atEnd());
        $directives->skip('stack_size', $tokens);
    }

    public function testSkipRejectsADirectiveWithoutItsCode(): void
    {
        $tokens = new LemonTokens((new LemonScanner())->scan('rest'));

        $this->expectException(GrammarSourceException::class);

        (new LemonDirectives())->skip('include', $tokens);
    }
}
