<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Grammar\Source\Bison\Directive\TypeDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Lexer\ActionScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexeme;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexer;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonScannerChain;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonToken;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTokenStream;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonTrivia;
use SqlFaker\Grammar\Source\Bison\Lexer\DirectiveScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\IdentifierScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\NumberScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\TypeTagScanner;
use SqlFaker\Grammar\Source\SourceCursor;

#[CoversClass(TypeDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(BisonTypeDeclaration::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class TypeDirectiveReaderTest extends TestCase
{
    public function testHandlesClaimsOnlyTheTypeDirective(): void
    {
        $reader = new TypeDirectiveReader();

        self::assertTrue($reader->handles('%type'));
        self::assertFalse($reader->handles('%token'));
    }

    public function testReadTakesTheTypeTagAndTheRulesItAppliesTo(): void
    {
        $declaration = (new TypeDirectiveReader())->read(BisonTokenStream::over('<num> expr term'), '%type');

        self::assertInstanceOf(BisonTypeDeclaration::class, $declaration);
        self::assertSame('num', $declaration->typeTag);
        self::assertSame(['expr', 'term'], $declaration->symbols);
    }

    public function testReadDeclaresNothingWithoutATypeTag(): void
    {
        self::assertNull((new TypeDirectiveReader())->read(BisonTokenStream::over('expr'), '%type'));
    }

    public function testReadSkipsWhatIsNotARuleName(): void
    {
        $declaration = (new TypeDirectiveReader())->read(BisonTokenStream::over('<num> expr 7 term'), '%type');

        self::assertInstanceOf(BisonTypeDeclaration::class, $declaration);
        self::assertSame(['expr', 'term'], $declaration->symbols);
    }

    public function testReadStopsAtTheNextDirective(): void
    {
        $stream = BisonTokenStream::over('<num> expr %token IDENT');

        (new TypeDirectiveReader())->read($stream, '%type');

        self::assertSame('%token', $stream->next()->value);
    }
}
