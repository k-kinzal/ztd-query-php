<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonAlternativeNode;
use SqlFaker\Compiler\Bison\Ast\BisonRuleNode;
use SqlFaker\Compiler\Bison\Ast\BisonSymbolNode;
use SqlFaker\Compiler\Bison\Lexer\ActionScanner;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonLexer;
use SqlFaker\Compiler\Bison\Lexer\BisonScannerChain;
use SqlFaker\Compiler\Bison\Lexer\BisonToken;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;
use SqlFaker\Compiler\Bison\Lexer\BisonTrivia;
use SqlFaker\Compiler\Bison\Lexer\DirectiveScanner;
use SqlFaker\Compiler\Bison\Lexer\IdentifierScanner;
use SqlFaker\Compiler\Bison\Lexer\NumberScanner;
use SqlFaker\Compiler\Bison\Lexer\PunctuationScanner;
use SqlFaker\Compiler\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Compiler\Bison\Lexer\TypeTagScanner;
use SqlFaker\Compiler\Bison\Rule\BisonAlternativeDraft;
use SqlFaker\Compiler\Bison\Rule\BisonAlternativeReader;
use SqlFaker\Compiler\Bison\Rule\BisonRuleReader;
use SqlFaker\Grammar\SourceCursor;

#[CoversClass(BisonRuleReader::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
#[UsesClass(PunctuationScanner::class)]
#[UsesClass(BisonAlternativeDraft::class)]
#[UsesClass(BisonAlternativeNode::class)]
#[UsesClass(BisonAlternativeReader::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonRuleNode::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonSymbolNode::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
final class BisonRuleReaderTest extends TestCase
{
    public function testReadAllKeepsTheRulesInTheOrderTheyWereDeclared(): void
    {
        $rules = (new BisonRuleReader())->readAll(BisonTokenStream::over('a : x ; b : y ; c : z ;'));

        self::assertSame(
            ['a', 'b', 'c'],
            array_map(static fn (BisonRuleNode $rule): string => $rule->name, $rules),
        );
    }

    public function testReadAllStopsAtTheSectionSeparator(): void
    {
        $stream = BisonTokenStream::over('a : x ; %% epilogue');

        $rules = (new BisonRuleReader())->readAll($stream);

        self::assertCount(1, $rules);
        self::assertSame('epilogue', $stream->next()->value);
    }

    public function testReadAllFindsNoRulesInAnEmptySection(): void
    {
        self::assertSame([], (new BisonRuleReader())->readAll(BisonTokenStream::over('')));
    }

    public function testReadAllSkipsWhatDoesNotOpenARule(): void
    {
        $rules = (new BisonRuleReader())->readAll(BisonTokenStream::over('42 ; a : x ;'));

        self::assertCount(1, $rules);
        self::assertSame('a', $rules[0]->name);
    }

    public function testReadTakesTheRuleNameAndItsAlternatives(): void
    {
        $rule = (new BisonRuleReader())->read(BisonTokenStream::over('expr : a | b ;'));

        self::assertInstanceOf(BisonRuleNode::class, $rule);
        self::assertSame('expr', $rule->name);
        self::assertCount(2, $rule->alternatives);
    }

    public function testReadFindsNoRuleWhenNoColonFollowsTheName(): void
    {
        self::assertNull((new BisonRuleReader())->read(BisonTokenStream::over('expr expr')));
    }

    public function testReadConsumesTheNameItRejectedSoTheScanCanAdvance(): void
    {
        $stream = BisonTokenStream::over('expr next : a ;');

        (new BisonRuleReader())->read($stream);

        self::assertSame('next', $stream->next()->value);
    }
}
