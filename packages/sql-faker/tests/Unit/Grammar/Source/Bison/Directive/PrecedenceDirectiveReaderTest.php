<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Grammar\Source\Bison\Directive\PrecedenceDirectiveReader;
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

#[CoversClass(PrecedenceDirectiveReader::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonPrecedenceDeclaration::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class PrecedenceDirectiveReaderTest extends TestCase
{
    #[DataProvider('providerAssociativityDirective')]
    public function testHandlesClaimsEveryAssociativityDirective(string $directive): void
    {
        self::assertTrue((new PrecedenceDirectiveReader())->handles($directive));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerAssociativityDirective(): iterable
    {
        yield 'left' => ['%left'];
        yield 'right' => ['%right'];
        yield 'nonassoc' => ['%nonassoc'];
        yield 'precedence' => ['%precedence'];
    }

    public function testHandlesRejectsADirectiveThatDeclaresNoAssociativity(): void
    {
        self::assertFalse((new PrecedenceDirectiveReader())->handles('%token'));
    }

    #[DataProvider('providerAssociativityDirective')]
    public function testReadTakesTheAssociativityFromTheDirectiveName(string $directive): void
    {
        $declaration = (new PrecedenceDirectiveReader())->read(BisonTokenStream::over('OR_SYM'), $directive);

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $declaration);
        self::assertSame(substr($directive, 1), $declaration->associativity);
    }

    public function testReadTakesOperatorsWrittenAsNamesOrAsCharacters(): void
    {
        $declaration = (new PrecedenceDirectiveReader())->read(BisonTokenStream::over("OR_SYM '+'"), '%left');

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $declaration);
        self::assertSame(['OR_SYM', '+'], $declaration->symbols);
    }

    public function testReadTakesTheOptionalTypeTag(): void
    {
        $declaration = (new PrecedenceDirectiveReader())->read(BisonTokenStream::over('<num> OR_SYM'), '%left');

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $declaration);
        self::assertSame('num', $declaration->typeTag);
    }

    public function testReadSkipsWhatIsNotAnOperator(): void
    {
        $declaration = (new PrecedenceDirectiveReader())->read(BisonTokenStream::over('OR_SYM 7 AND_SYM'), '%left');

        self::assertInstanceOf(BisonPrecedenceDeclaration::class, $declaration);
        self::assertSame(['OR_SYM', 'AND_SYM'], $declaration->symbols);
    }

    public function testReadStopsAtTheSectionSeparator(): void
    {
        $stream = BisonTokenStream::over('OR_SYM %% rules');

        (new PrecedenceDirectiveReader())->read($stream, '%left');

        self::assertSame('%%', $stream->next()->value);
    }
}
