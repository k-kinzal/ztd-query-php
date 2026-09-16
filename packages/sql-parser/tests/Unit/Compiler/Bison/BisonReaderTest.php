<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonDeclarations;
use SqlParser\Compiler\Bison\BisonReader;
use SqlParser\Compiler\Bison\BisonRules;
use SqlParser\Compiler\Bison\BisonScanner;
use SqlParser\Compiler\Bison\BisonToken;
use SqlParser\Compiler\Bison\BisonTokens;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Grammar\UnknownSymbolException;

#[CoversClass(BisonReader::class)]
#[UsesClass(BisonDeclarations::class)]
#[UsesClass(BisonRules::class)]
#[UsesClass(BisonScanner::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokens::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(SourceReader::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(UnknownSymbolException::class)]
#[Small]
final class BisonReaderTest extends TestCase
{
    public function testReadBuildsTheGrammarOfAFile(): void
    {
        $source = "%{ int x; %}\n%token NUM\n%left '+'\n%%\nexpr: expr '+' expr | NUM ;\n%%\nvoid f() {}\n";
        $grammar = (new BisonReader())->read($source);

        self::assertSame(['$end', 'error', 'NUM', '+'], $grammar->symbols->terminals());
        self::assertSame(['$accept', 'expr'], $grammar->symbols->nonterminals());
        self::assertCount(3, $grammar->rules);
        self::assertSame(PrecedencePolicy::LastTerminal, $grammar->policy);
        self::assertSame('expr', $grammar->symbols->name($grammar->startSymbol()));
    }

    public function testReadRejectsAnUndeclaredSymbol(): void
    {
        $this->expectException(UnknownSymbolException::class);

        (new BisonReader())->read("%%\nexpr: NUM ;");
    }

    public function testReadRejectsAFileWithoutRules(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new BisonReader())->read("%token A\n%%\n: ;");
    }
}
