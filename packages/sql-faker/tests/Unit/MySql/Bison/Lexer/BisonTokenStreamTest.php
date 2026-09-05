<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\ActionScanner;
use SqlFaker\MySql\Bison\Lexer\BisonScannerChain;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenScanner;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;
use SqlFaker\MySql\Bison\Lexer\IdentifierScanner;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;
use SqlFaker\MySql\Bison\Lexer\PunctuationScanner;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;

#[CoversClass(BisonTokenStream::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(BisonTokenType::class)]
#[UsesClass(BisonTokenScanner::class)]
#[UsesClass(BisonScannerChain::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(BisonTrivia::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(GrammarParseException::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(PunctuationScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(SourceCursor::class)]
#[UsesClass(TypeTagScanner::class)]
final class BisonTokenStreamTest extends TestCase
{
    public function testNextReturnsEofForEmptyInput(): void
    {
        $lexer = BisonTokenStream::over('');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
        self::assertSame('', $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testNextReturnsEofForWhitespaceOnlyInput(): void
    {
        $lexer = BisonTokenStream::over("   \t\n\r  ");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
    }

    public function testNextReturnsEofAfterEof(): void
    {
        $lexer = BisonTokenStream::over('x');

        $lexer->next();
        $eof1 = $lexer->next();
        $eof2 = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $eof1->type);
        self::assertSame(BisonTokenType::Eof, $eof2->type);
    }

    public function testNextReturnsTokenFromBuffer(): void
    {
        $lexer = BisonTokenStream::over('foo bar');

        $peeked = $lexer->peek();
        $token = $lexer->next();

        self::assertSame($peeked->type, $token->type);
        self::assertSame($peeked->value, $token->value);
    }


    public function testNextSkipsWhitespace(): void
    {
        $lexer = BisonTokenStream::over("   \t\n\r  foo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsLineComment(): void
    {
        $lexer = BisonTokenStream::over("// this is a comment\nfoo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsLineCommentAtEof(): void
    {
        $lexer = BisonTokenStream::over('// comment without newline');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
    }

    public function testNextSkipsBlockComment(): void
    {
        $lexer = BisonTokenStream::over('/* block comment */ foo');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsBlockCommentMultiline(): void
    {
        $input = <<<'INPUT'
/* line 1
   line 2
   line 3 */
foo
INPUT;
        $lexer = BisonTokenStream::over($input);

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsBlockCommentUnterminated(): void
    {
        $lexer = BisonTokenStream::over('/* unterminated');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
    }

    public function testNextSkipsConsecutiveLineComments(): void
    {
        $lexer = BisonTokenStream::over("// first\n// second\nfoo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsConsecutiveBlockComments(): void
    {
        $lexer = BisonTokenStream::over('/* first */ /* second */ foo');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsMixedComments(): void
    {
        $lexer = BisonTokenStream::over("/* block */ // line\nfoo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }


    public function testNextIdentifier(): void
    {
        $lexer = BisonTokenStream::over('simple_ident');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('simple_ident', $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testNextIdentifierSingleChar(): void
    {
        $lexer = BisonTokenStream::over('a');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('a', $token->value);
    }

    public function testNextIdentifierWithDigits(): void
    {
        $lexer = BisonTokenStream::over('foo123');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo123', $token->value);
    }

    public function testNextIdentifierWithDots(): void
    {
        $lexer = BisonTokenStream::over('api.pure.full');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('api.pure.full', $token->value);
    }

    public function testNextIdentifierWithUnderscore(): void
    {
        $lexer = BisonTokenStream::over('_private');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('_private', $token->value);
    }

    public function testNextIdentifierUpperCase(): void
    {
        $lexer = BisonTokenStream::over('SELECT_SYM');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('SELECT_SYM', $token->value);
    }


    public function testNextNumber(): void
    {
        $lexer = BisonTokenStream::over('12345');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Number, $token->type);
        self::assertSame(12345, $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testNextNumberSingleDigit(): void
    {
        $lexer = BisonTokenStream::over('7');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Number, $token->type);
        self::assertSame(7, $token->value);
    }

    public function testNextNumberZero(): void
    {
        $lexer = BisonTokenStream::over('0');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Number, $token->type);
        self::assertSame(0, $token->value);
    }


    public function testNextStringLiteral(): void
    {
        $lexer = BisonTokenStream::over('"hello world"');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('hello world', $token->value);
    }

    public function testNextStringLiteralEmpty(): void
    {
        $lexer = BisonTokenStream::over('""');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextStringLiteralWithEscapedQuote(): void
    {
        $lexer = BisonTokenStream::over('"say \"hi\""');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('say "hi"', $token->value);
    }

    public function testNextStringLiteralWithBackslash(): void
    {
        $lexer = BisonTokenStream::over('"path\\\\to\\\\file"');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('path\\to\\file', $token->value);
    }

    public function testNextStringLiteralUnterminated(): void
    {
        $lexer = BisonTokenStream::over('"unterminated');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('unterminated', $token->value);
    }

    public function testNextStringLiteralEscapeAtEnd(): void
    {
        $lexer = BisonTokenStream::over('"end\\');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('end', $token->value);
    }


    public function testNextCharLiteral(): void
    {
        $lexer = BisonTokenStream::over("'c'");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('c', $token->value);
    }

    public function testNextCharLiteralEmpty(): void
    {
        $lexer = BisonTokenStream::over("''");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextCharLiteralWithEscape(): void
    {
        $lexer = BisonTokenStream::over("'\\n'");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('n', $token->value);
    }

    public function testNextCharLiteralUnterminated(): void
    {
        $lexer = BisonTokenStream::over("'x");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('x', $token->value);
    }


    public function testNextColon(): void
    {
        $lexer = BisonTokenStream::over(':');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Colon, $token->type);
        self::assertSame(':', $token->value);
    }

    public function testNextSemicolon(): void
    {
        $lexer = BisonTokenStream::over(';');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Semicolon, $token->type);
        self::assertSame(';', $token->value);
    }

    public function testNextPipe(): void
    {
        $lexer = BisonTokenStream::over('|');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Pipe, $token->type);
        self::assertSame('|', $token->value);
    }

    public function testNextPercentPercent(): void
    {
        $lexer = BisonTokenStream::over('%%');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::PercentPercent, $token->type);
        self::assertSame('%%', $token->value);
    }


    public function testNextDirective(): void
    {
        $lexer = BisonTokenStream::over('%token');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%token', $token->value);
    }

    public function testNextDirectiveWithHyphen(): void
    {
        $lexer = BisonTokenStream::over('%parse-param');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%parse-param', $token->value);
    }

    public function testNextDirectiveWithNumbers(): void
    {
        $lexer = BisonTokenStream::over('%define123');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%define123', $token->value);
    }

    public function testNextDirectiveWithDots(): void
    {
        $lexer = BisonTokenStream::over('%api.pure');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%api.pure', $token->value);
    }


    public function testNextTypeTag(): void
    {
        $lexer = BisonTokenStream::over('<node_type>');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('node_type', $token->value);
    }

    public function testNextTypeTagEmpty(): void
    {
        $lexer = BisonTokenStream::over('<>');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextTypeTagTrimmed(): void
    {
        $lexer = BisonTokenStream::over('<  spaced  >');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('spaced', $token->value);
    }

    public function testNextTypeTagWithSpecialChars(): void
    {
        $lexer = BisonTokenStream::over('<Item*>');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('Item*', $token->value);
    }


    public function testNextPrologue(): void
    {
        $lexer = BisonTokenStream::over('%{ #include <stdio.h> %}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame(' #include <stdio.h> ', $token->value);
    }

    public function testNextPrologueEmpty(): void
    {
        $lexer = BisonTokenStream::over('%{%}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextPrologueWithWhitespaceOnly(): void
    {
        $lexer = BisonTokenStream::over('%{   %}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame('   ', $token->value);
    }

    public function testNextPrologueUnterminated(): void
    {
        $lexer = BisonTokenStream::over('%{ incomplete prologue');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame(' incomplete prologue', $token->value);
    }


    public function testNextAction(): void
    {
        $lexer = BisonTokenStream::over('{ $$ = $1; }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' $$ = $1; ', $token->value);
    }

    public function testNextActionEmpty(): void
    {
        $lexer = BisonTokenStream::over('{}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextActionWithWhitespaceOnly(): void
    {
        $lexer = BisonTokenStream::over('{   }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame('   ', $token->value);
    }

    public function testNextActionNested(): void
    {
        $lexer = BisonTokenStream::over('{ if (x) { y(); } }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' if (x) { y(); } ', $token->value);
    }

    public function testNextActionDeeplyNested(): void
    {
        $lexer = BisonTokenStream::over('{ a { b { c } d } e }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' a { b { c } d } e ', $token->value);
    }

    public function testNextActionWithStringContainingBrace(): void
    {
        $lexer = BisonTokenStream::over('{ printf("{"); }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' printf("{"); ', $token->value);
    }

    public function testNextActionWithCharLiteralContainingBrace(): void
    {
        $lexer = BisonTokenStream::over("{ char c = '{'; }");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(" char c = '{'; ", $token->value);
    }

    public function testNextActionWithLineComment(): void
    {
        $lexer = BisonTokenStream::over("{ x = 1; // comment\n y = 2; }");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertIsString($token->value);
        self::assertStringContainsString('x = 1;', $token->value);
        self::assertStringContainsString('y = 2;', $token->value);
    }

    public function testNextActionWithBlockComment(): void
    {
        $lexer = BisonTokenStream::over('{ x = 1; /* { not a brace } */ y = 2; }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertIsString($token->value);
        self::assertStringContainsString('x = 1;', $token->value);
        self::assertStringContainsString('y = 2;', $token->value);
    }

    public function testNextActionUnterminated(): void
    {
        $lexer = BisonTokenStream::over('{ incomplete action');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' incomplete action', $token->value);
    }

    public function testNextActionWithUnterminatedString(): void
    {
        $lexer = BisonTokenStream::over('{ "unterminated }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertIsString($token->value);
    }


    public function testNextMultipleTokens(): void
    {
        $lexer = BisonTokenStream::over('%token FOO 123 "alias"');

        $t1 = $lexer->next();
        $t2 = $lexer->next();
        $t3 = $lexer->next();
        $t4 = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $t1->type);
        self::assertSame('%token', $t1->value);

        self::assertSame(BisonTokenType::Identifier, $t2->type);
        self::assertSame('FOO', $t2->value);

        self::assertSame(BisonTokenType::Number, $t3->type);
        self::assertSame(123, $t3->value);

        self::assertSame(BisonTokenType::StringLiteral, $t4->type);
        self::assertSame('alias', $t4->value);
    }

    public function testNextTracksOffset(): void
    {
        $lexer = BisonTokenStream::over('foo  bar');

        $t1 = $lexer->next();
        $t2 = $lexer->next();

        self::assertSame(0, $t1->offset);
        self::assertSame(5, $t2->offset);
    }

    public function testNextTracksOffsetAfterWhitespace(): void
    {
        $lexer = BisonTokenStream::over('   foo');

        $token = $lexer->next();

        self::assertSame(3, $token->offset);
    }

    public function testNextTracksOffsetAfterComment(): void
    {
        $lexer = BisonTokenStream::over('/* x */ foo');

        $token = $lexer->next();

        self::assertSame(8, $token->offset);
    }


    public function testNextThrowsForUnexpectedCharacter(): void
    {
        $lexer = BisonTokenStream::over('@');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected character '@' at offset 0");

        $lexer->next();
    }

    public function testNextThrowsForUnexpectedSlash(): void
    {
        $lexer = BisonTokenStream::over('/ not a comment');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected '/' at offset 0");

        $lexer->next();
    }

    public function testNextThrowsForEmptyDirective(): void
    {
        $lexer = BisonTokenStream::over('% ');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected '%' at offset 0");

        $lexer->next();
    }

    public function testNextThrowsForUnterminatedTypeTag(): void
    {
        $lexer = BisonTokenStream::over('<unterminated');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage('Unterminated type tag starting at offset 0');

        $lexer->next();
    }

    public function testNextThrowsForDigitStartingIdentifierPosition(): void
    {
        $lexer = BisonTokenStream::over('123abc');

        $t1 = $lexer->next();
        $t2 = $lexer->next();

        self::assertSame(BisonTokenType::Number, $t1->type);
        self::assertSame(123, $t1->value);
        self::assertSame(BisonTokenType::Identifier, $t2->type);
        self::assertSame('abc', $t2->value);
    }


    public function testNextCompleteGrammarFragment(): void
    {
        $input = <<<'YY'
%{
#include <stdio.h>
%}
%start expr
%token <num> NUMBER 100
%left PLUS MINUS
%%
expr:
      NUMBER
    | expr PLUS expr { $$ = $1 + $3; }
;
YY;

        $lexer = BisonTokenStream::over($input);
        $t0 = $lexer->next();
        $t1 = $lexer->next();
        $t2 = $lexer->next();
        $t3 = $lexer->next();
        $t4 = $lexer->next();
        $t5 = $lexer->next();
        $t6 = $lexer->next();
        $t7 = $lexer->next();
        $t8 = $lexer->next();
        $t9 = $lexer->next();
        $t10 = $lexer->next();
        $t11 = $lexer->next();
        $t12 = $lexer->next();
        $t13 = $lexer->next();
        $t14 = $lexer->next();
        $t15 = $lexer->next();
        $t16 = $lexer->next();
        $t17 = $lexer->next();
        $t18 = $lexer->next();
        $t19 = $lexer->next();
        $t20 = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $t0->type);
        self::assertSame(BisonTokenType::Directive, $t1->type);
        self::assertSame(BisonTokenType::Identifier, $t2->type);
        self::assertSame(BisonTokenType::Directive, $t3->type);
        self::assertSame(BisonTokenType::TypeTag, $t4->type);
        self::assertSame(BisonTokenType::Identifier, $t5->type);
        self::assertSame(BisonTokenType::Number, $t6->type);
        self::assertSame(BisonTokenType::Directive, $t7->type);
        self::assertSame(BisonTokenType::Identifier, $t8->type);
        self::assertSame(BisonTokenType::Identifier, $t9->type);
        self::assertSame(BisonTokenType::PercentPercent, $t10->type);
        self::assertSame(BisonTokenType::Identifier, $t11->type);
        self::assertSame(BisonTokenType::Colon, $t12->type);
        self::assertSame(BisonTokenType::Identifier, $t13->type);
        self::assertSame(BisonTokenType::Pipe, $t14->type);
        self::assertSame(BisonTokenType::Identifier, $t15->type);
        self::assertSame(BisonTokenType::Identifier, $t16->type);
        self::assertSame(BisonTokenType::Identifier, $t17->type);
        self::assertSame(BisonTokenType::Action, $t18->type);
        self::assertSame(BisonTokenType::Semicolon, $t19->type);
        self::assertSame(BisonTokenType::Eof, $t20->type);
    }

    #[DataProvider('providerNextTokenTypes')]
    public function testNextTokenTypes(string $input, BisonTokenType $expectedType, string|int $expectedValue): void
    {
        $lexer = BisonTokenStream::over($input);

        $token = $lexer->next();

        self::assertSame($expectedType, $token->type);
        self::assertSame($expectedValue, $token->value);
    }

    public function testPeekReturnsSameToken(): void
    {
        $lexer = BisonTokenStream::over('identifier');

        $first = $lexer->peek();
        $second = $lexer->peek();

        self::assertSame($first->type, $second->type);
        self::assertSame($first->value, $second->value);
        self::assertSame($first->offset, $second->offset);
    }

    public function testPeekDoesNotConsume(): void
    {
        $lexer = BisonTokenStream::over('foo bar');

        $peeked = $lexer->peek();
        $next = $lexer->next();

        self::assertSame($peeked->value, $next->value);
        self::assertSame('foo', $next->value);
    }

    public function testPeekThenNextThenPeek(): void
    {
        $lexer = BisonTokenStream::over('foo bar baz');

        $peek1 = $lexer->peek();
        self::assertSame('foo', $peek1->value);

        $next1 = $lexer->next();
        self::assertSame('foo', $next1->value);

        $peek2 = $lexer->peek();
        self::assertSame('bar', $peek2->value);
    }


    public function testPeekNReturnsNthToken(): void
    {
        $lexer = BisonTokenStream::over('foo bar baz');

        $first = $lexer->peekN(1);
        $second = $lexer->peekN(2);
        $third = $lexer->peekN(3);

        self::assertSame('foo', $first->value);
        self::assertSame('bar', $second->value);
        self::assertSame('baz', $third->value);
    }

    public function testPeekNReturnsSameResult(): void
    {
        $lexer = BisonTokenStream::over('a b c');

        $first1 = $lexer->peekN(1);
        $second1 = $lexer->peekN(2);
        $first2 = $lexer->peekN(1);
        $second2 = $lexer->peekN(2);

        self::assertSame($first1->value, $first2->value);
        self::assertSame($second1->value, $second2->value);
    }

    public function testPeekNAfterPartialConsumption(): void
    {
        $lexer = BisonTokenStream::over('a b c d');

        $lexer->peekN(3);

        $lexer->next();

        $peek1 = $lexer->peekN(1);
        self::assertSame('b', $peek1->value);

        $peek2 = $lexer->peekN(2);
        self::assertSame('c', $peek2->value);
    }

    public function testPeekNThenPeekNSmaller(): void
    {
        $lexer = BisonTokenStream::over('a b c');

        $third = $lexer->peekN(3);
        self::assertSame('c', $third->value);

        $first = $lexer->peekN(1);
        self::assertSame('a', $first->value);
    }

    public function testNextIfConsumesATokenOfAnAcceptedKind(): void
    {
        $stream = BisonTokenStream::over('foo bar');

        self::assertSame('foo', $stream->nextIf(BisonTokenType::Identifier)?->value);
        self::assertSame('bar', $stream->next()->value);
    }

    public function testNextIfAcceptsAnyOfSeveralKinds(): void
    {
        $stream = BisonTokenStream::over('42');

        self::assertSame(42, $stream->nextIf(BisonTokenType::Identifier, BisonTokenType::Number)?->value);
    }

    public function testNextIfLeavesTheStreamUnmovedWhenTheKindDoesNotMatch(): void
    {
        $stream = BisonTokenStream::over('foo');

        self::assertNull($stream->nextIf(BisonTokenType::Number));
        self::assertSame('foo', $stream->next()->value);
    }

    public function testNextIfAcceptsNothingWhenNoKindIsGiven(): void
    {
        $stream = BisonTokenStream::over('foo');

        self::assertNull($stream->nextIf());
        self::assertSame('foo', $stream->next()->value);
    }
    public function testOverReadsTheSourceItWasGiven(): void
    {
        self::assertSame('foo', BisonTokenStream::over('foo')->next()->value);
    }

    public function testNextIntReadsANumericTokenAsAnInteger(): void
    {
        self::assertSame(42, BisonTokenStream::over('42')->nextInt());
    }

    public function testNextIntReadsANonNumericTokenAsZero(): void
    {
        self::assertSame(0, BisonTokenStream::over('foo')->nextInt());
    }

    public function testConsumeRemainingTakesEverythingAfterTheTokensAlreadyRead(): void
    {
        $stream = BisonTokenStream::over('foo @ % <');

        $stream->next();

        self::assertSame(' @ % <', $stream->consumeRemaining());
    }

    public function testConsumeRemainingDiscardsBufferedLookahead(): void
    {
        $stream = BisonTokenStream::over('foo bar @');

        $stream->peekN(2);

        self::assertSame(' @', $stream->consumeRemaining());
    }

    /**
     * @return iterable<string, array{string, BisonTokenType, string|int}>
     */
    public static function providerNextTokenTypes(): iterable
    {
        yield 'identifier' => ['foo', BisonTokenType::Identifier, 'foo'];
        yield 'identifier with digits' => ['foo123', BisonTokenType::Identifier, 'foo123'];
        yield 'number' => ['42', BisonTokenType::Number, 42];
        yield 'number zero' => ['0', BisonTokenType::Number, 0];
        yield 'string literal' => ['"str"', BisonTokenType::StringLiteral, 'str'];
        yield 'char literal' => ["'x'", BisonTokenType::CharLiteral, 'x'];
        yield 'colon' => [':', BisonTokenType::Colon, ':'];
        yield 'semicolon' => [';', BisonTokenType::Semicolon, ';'];
        yield 'pipe' => ['|', BisonTokenType::Pipe, '|'];
        yield 'percent percent' => ['%%', BisonTokenType::PercentPercent, '%%'];
        yield 'directive token' => ['%token', BisonTokenType::Directive, '%token'];
        yield 'directive start' => ['%start', BisonTokenType::Directive, '%start'];
        yield 'directive left' => ['%left', BisonTokenType::Directive, '%left'];
        yield 'type tag' => ['<type>', BisonTokenType::TypeTag, 'type'];
        yield 'prologue' => ['%{ code %}', BisonTokenType::Prologue, ' code '];
        yield 'action' => ['{ code }', BisonTokenType::Action, ' code '];
    }
}
