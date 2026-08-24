<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\MySql\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\MySql\Bison\Directive\TypeDirectiveReader;
use SqlFaker\MySql\Bison\Lexer\BisonLexeme;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;

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
