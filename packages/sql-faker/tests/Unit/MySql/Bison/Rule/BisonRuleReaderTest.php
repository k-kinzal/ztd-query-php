<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Lexer\ActionScanner;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;
use SqlFaker\MySql\Bison\Lexer\IdentifierScanner;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;
use SqlFaker\MySql\Bison\Lexer\PunctuationScanner;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;
use SqlFaker\MySql\Bison\Rule\BisonAlternativeDraft;
use SqlFaker\MySql\Bison\Rule\BisonAlternativeReader;
use SqlFaker\MySql\Bison\Rule\BisonRuleReader;

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
