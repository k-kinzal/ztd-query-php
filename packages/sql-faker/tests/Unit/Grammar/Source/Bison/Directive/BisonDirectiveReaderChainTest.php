<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonDefineDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonParamDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTypeDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDeclarationBoundary;
use SqlFaker\Grammar\Source\Bison\Directive\BisonDirectiveReaderChain;
use SqlFaker\Grammar\Source\Bison\Directive\DefineDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\ExpectDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\ParamDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\PrecedenceDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\StartDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\TokenDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\TypeDirectiveReader;
use SqlFaker\Grammar\Source\Bison\Directive\UnknownDirectiveReader;
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

#[CoversClass(BisonDirectiveReaderChain::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(BisonDeclarationBoundary::class)]
#[UsesClass(BisonDefineDeclaration::class)]
#[UsesClass(BisonExpectDeclaration::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonLexer::class)]
#[UsesClass(BisonParamDeclaration::class)]
#[UsesClass(BisonPrecedenceDeclaration::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonStartDeclaration::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTokenDeclaration::class)]
#[UsesClass(BisonTokenDefinition::class)]
#[UsesClass(BisonTokenStream::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(BisonTypeDeclaration::class)]
#[UsesClass(BisonUnknownDeclaration::class)]
#[UsesClass(DefineDirectiveReader::class)]
#[UsesClass(ExpectDirectiveReader::class)]
#[UsesClass(ParamDirectiveReader::class)]
#[UsesClass(PrecedenceDirectiveReader::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(StartDirectiveReader::class)]
#[UsesClass(TokenDirectiveReader::class)]
#[UsesClass(TypeDirectiveReader::class)]
#[UsesClass(UnknownDirectiveReader::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class BisonDirectiveReaderChainTest extends TestCase
{
    /**
     * @param class-string<BisonDeclaration> $expected
     */
    #[DataProvider('providerDirective')]
    public function testReadRoutesEachDirectiveToTheReaderThatKnowsIt(
        string $directive,
        string $arguments,
        string $expected,
    ): void {
        $declaration = BisonDirectiveReaderChain::forBisonGrammar()
            ->read(BisonTokenStream::over($arguments), $directive);

        self::assertInstanceOf($expected, $declaration);
    }

    /**
     * @return iterable<string, array{string, string, class-string<BisonDeclaration>}>
     */
    public static function providerDirective(): iterable
    {
        yield 'start' => ['%start', 'statement', BisonStartDeclaration::class];
        yield 'token' => ['%token', 'IDENT', BisonTokenDeclaration::class];
        yield 'type' => ['%type', '<num> expr', BisonTypeDeclaration::class];
        yield 'left' => ['%left', 'OR_SYM', BisonPrecedenceDeclaration::class];
        yield 'right' => ['%right', 'NOT_SYM', BisonPrecedenceDeclaration::class];
        yield 'parse-param' => ['%parse-param', '{ THD *thd }', BisonParamDeclaration::class];
        yield 'expect' => ['%expect', '3', BisonExpectDeclaration::class];
        yield 'define' => ['%define', 'api.pure', BisonDefineDeclaration::class];
        yield 'anything else' => ['%glr-parser', '', BisonUnknownDeclaration::class];
    }

    public function testReadFallsBackToTheUnknownReaderRatherThanDroppingADirective(): void
    {
        $declaration = BisonDirectiveReaderChain::forBisonGrammar()
            ->read(BisonTokenStream::over('a b'), '%no-such-directive');

        self::assertInstanceOf(BisonUnknownDeclaration::class, $declaration);
        self::assertSame('a b', $declaration->content);
    }

    public function testReadDeclaresNothingWhenNoReaderClaimsTheDirective(): void
    {
        self::assertNull(
            (new BisonDirectiveReaderChain([]))->read(BisonTokenStream::over('x'), '%start'),
        );
    }

    public function testForBisonGrammarConsultsTheUnknownReaderLast(): void
    {
        $declaration = BisonDirectiveReaderChain::forBisonGrammar()
            ->read(BisonTokenStream::over('statement'), '%start');

        self::assertInstanceOf(BisonStartDeclaration::class, $declaration);
    }
}
