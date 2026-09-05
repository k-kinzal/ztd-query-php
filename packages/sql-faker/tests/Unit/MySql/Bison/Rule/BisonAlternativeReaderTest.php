<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolForm;
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

#[CoversClass(BisonAlternativeReader::class)]
#[UsesClass(BisonAlternativeDraft::class)]
#[UsesClass(BisonAlternativeNode::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonSymbolNode::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(PunctuationScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class BisonAlternativeReaderTest extends TestCase
{
    public function testReadAllTakesTheSymbolsUpToTheSemicolon(): void
    {
        $alternatives = (new BisonAlternativeReader())->readAll(BisonTokenStream::over('a b ;'));

        self::assertCount(1, $alternatives);
        self::assertSame(
            ['a', 'b'],
            array_map(static fn (BisonSymbolNode $symbol): string => $symbol->value, $alternatives[0]->symbols),
        );
    }

    public function testReadAllSplitsAlternativesOnThePipe(): void
    {
        $alternatives = (new BisonAlternativeReader())->readAll(BisonTokenStream::over('a | b | ;'));

        self::assertCount(3, $alternatives);
        self::assertCount(1, $alternatives[0]->symbols);
        self::assertCount(1, $alternatives[1]->symbols);
        self::assertSame([], $alternatives[2]->symbols);
    }

    public function testReadAllStopsWhereTheNextRuleBegins(): void
    {
        $stream = BisonTokenStream::over('a b next_rule : c ;');

        $alternatives = (new BisonAlternativeReader())->readAll($stream);

        self::assertCount(1, $alternatives);
        self::assertCount(2, $alternatives[0]->symbols);
        self::assertSame('next_rule', $stream->next()->value);
    }

    public function testReadAllStopsAtTheEndOfTheSection(): void
    {
        $alternatives = (new BisonAlternativeReader())->readAll(BisonTokenStream::over('a b'));

        self::assertCount(1, $alternatives);
    }

    public function testReadAllKeepsEachAlternativesOwnAction(): void
    {
        $alternatives = (new BisonAlternativeReader())
            ->readAll(BisonTokenStream::over('a { first } | b { second } ;'));

        self::assertSame(' first ', $alternatives[0]->action);
        self::assertSame(' second ', $alternatives[1]->action);
    }

    public function testReadAllDoesNotCarryAnActionIntoTheNextAlternative(): void
    {
        $alternatives = (new BisonAlternativeReader())->readAll(BisonTokenStream::over('a { only } | b ;'));

        self::assertSame(' only ', $alternatives[0]->action);
        self::assertNull($alternatives[1]->action);
    }

    public function testEndsTheRuleReportsTheEndOfTheSection(): void
    {
        self::assertTrue((new BisonAlternativeReader())->endsTheRule(BisonTokenStream::over('')));
        self::assertTrue((new BisonAlternativeReader())->endsTheRule(BisonTokenStream::over('%%')));
    }

    public function testEndsTheRuleReportsTheStartOfTheNextRule(): void
    {
        self::assertTrue((new BisonAlternativeReader())->endsTheRule(BisonTokenStream::over('next :')));
    }

    public function testEndsTheRuleTreatsALoneNameAsPartOfTheAlternative(): void
    {
        self::assertFalse((new BisonAlternativeReader())->endsTheRule(BisonTokenStream::over('a b')));
    }

    public function testEndsTheRuleConsumesNothing(): void
    {
        $stream = BisonTokenStream::over('next :');

        (new BisonAlternativeReader())->endsTheRule($stream);

        self::assertSame('next', $stream->next()->value);
    }

    public function testReadPartAddsANamedSymbol(): void
    {
        $draft = new BisonAlternativeDraft();

        (new BisonAlternativeReader())->readPart(BisonTokenStream::over('expr'), $draft);

        self::assertSame(BisonSymbolForm::Identifier, $draft->complete()->symbols[0]->type);
    }

    public function testReadPartAddsACharacterSymbol(): void
    {
        $draft = new BisonAlternativeDraft();

        (new BisonAlternativeReader())->readPart(BisonTokenStream::over("'+'"), $draft);

        self::assertSame(BisonSymbolForm::CharLiteral, $draft->complete()->symbols[0]->type);
    }

    public function testReadPartSkipsWhatBelongsToNoAlternative(): void
    {
        $draft = new BisonAlternativeDraft();
        $stream = BisonTokenStream::over('42 expr');

        (new BisonAlternativeReader())->readPart($stream, $draft);

        self::assertSame([], $draft->complete()->symbols);
        self::assertSame('expr', $stream->next()->value);
    }

    public function testReadInlineDirectiveAppliesPrecedence(): void
    {
        $draft = new BisonAlternativeDraft();

        (new BisonAlternativeReader())->readInlineDirective(BisonTokenStream::over('%prec UMINUS'), $draft);

        self::assertSame('UMINUS', $draft->complete()->prec);
    }

    public function testReadInlineDirectiveIgnoresADirectiveItDoesNotModel(): void
    {
        $draft = new BisonAlternativeDraft();

        (new BisonAlternativeReader())->readInlineDirective(BisonTokenStream::over('%empty'), $draft);

        self::assertNull($draft->complete()->prec);
    }

    public function testReadPrecedenceSymbolTakesANameOrACharacter(): void
    {
        $reader = new BisonAlternativeReader();

        self::assertSame('UMINUS', $reader->readPrecedenceSymbol(BisonTokenStream::over('UMINUS')));
        self::assertSame('+', $reader->readPrecedenceSymbol(BisonTokenStream::over("'+'")));
    }

    public function testReadPrecedenceSymbolReportsNothingWhenNoneIsNamed(): void
    {
        self::assertNull((new BisonAlternativeReader())->readPrecedenceSymbol(BisonTokenStream::over('42')));
    }

    public function testReadDynamicPrecedenceTakesTheRank(): void
    {
        self::assertSame(2, (new BisonAlternativeReader())->readDynamicPrecedence(BisonTokenStream::over('2')));
    }

    public function testReadDynamicPrecedenceReportsNothingWhenNoneIsNamed(): void
    {
        self::assertNull((new BisonAlternativeReader())->readDynamicPrecedence(BisonTokenStream::over('x')));
    }

    public function testReadMergeFunctionTakesTheTaggedName(): void
    {
        self::assertSame('merge', (new BisonAlternativeReader())->readMergeFunction(BisonTokenStream::over('<merge>')));
    }

    public function testReadMergeFunctionReportsNothingWhenNoneIsNamed(): void
    {
        self::assertNull((new BisonAlternativeReader())->readMergeFunction(BisonTokenStream::over('merge')));
    }
}
