<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonCondition;
use SqlParser\Compiler\Lemon\LemonDirectives;
use SqlParser\Compiler\Lemon\LemonPreprocessor;
use SqlParser\Compiler\Lemon\LemonReader;
use SqlParser\Compiler\Lemon\LemonRules;
use SqlParser\Compiler\Lemon\LemonScanner;
use SqlParser\Compiler\Lemon\LemonToken;
use SqlParser\Compiler\Lemon\LemonTokens;
use SqlParser\Compiler\SourceReader;
use SqlParser\Grammar\Grammar;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Grammar\Precedence;
use SqlParser\Grammar\PrecedencePolicy;
use SqlParser\Grammar\Rule;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Grammar\UnknownSymbolException;

#[CoversClass(LemonReader::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(LemonCondition::class)]
#[UsesClass(LemonDirectives::class)]
#[UsesClass(LemonPreprocessor::class)]
#[UsesClass(LemonRules::class)]
#[UsesClass(LemonScanner::class)]
#[UsesClass(LemonToken::class)]
#[UsesClass(LemonTokens::class)]
#[UsesClass(SourceReader::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(Precedence::class)]
#[UsesClass(Rule::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(UnknownSymbolException::class)]
#[Small]
final class LemonReaderTest extends TestCase
{
    public function testReadBuildsTheGrammarOfAFile(): void
    {
        $source = "%token_prefix TK_\n%left PLUS.\n%token_class id ID|INDEXED.\n%fallback ID ABORT.\n%wildcard ANY.\ninput ::= cmd.\ncmd ::= id PLUS expr. { x(); }\n%ifdef WINDOW\ncmd ::= WINDOW.\n%endif\nexpr ::= ABORT|ANY.";
        $grammar = (new LemonReader())->read($source);

        self::assertSame('input', $grammar->symbols->name($grammar->startSymbol()));
        self::assertSame(PrecedencePolicy::FirstRankedTerminal, $grammar->policy);
        self::assertCount(4, $grammar->rules);
        self::assertSame($grammar->symbols->id('ID'), $grammar->fallbacks[$grammar->symbols->id('ABORT') ?? -1]);
        self::assertCount(2, $grammar->tokenClasses);
        self::assertCount(5, (new LemonReader())->read($source, ['WINDOW'])->rules);
    }

    public function testReadRejectsAnUndeclaredNonterminal(): void
    {
        $this->expectException(UnknownSymbolException::class);

        (new LemonReader())->read('input ::= missing.');
    }

    public function testReadRejectsAFileWithoutRules(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonReader())->read('::= x.');
    }
}
