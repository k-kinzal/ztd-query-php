<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\MySql\Bison\Lexer\BisonLexer;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;

final class BisonLexerTest extends TestCase
{
    public function testNextReturnsEofForEmptyInput(): void
    {
        $lexer = new BisonLexer('');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
        self::assertSame('', $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testNextReturnsEofForWhitespaceOnlyInput(): void
    {
        $lexer = new BisonLexer("   \t\n\r  ");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
    }

    public function testNextReturnsEofAfterEof(): void
    {
        $lexer = new BisonLexer('x');

        $lexer->next();
        $eof1 = $lexer->next();
        $eof2 = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $eof1->type);
        self::assertSame(BisonTokenType::Eof, $eof2->type);
    }

    public function testNextReturnsTokenFromBuffer(): void
    {
        $lexer = new BisonLexer('foo bar');

        $peeked = $lexer->peek();
        $token = $lexer->next();

        self::assertSame($peeked->type, $token->type);
        self::assertSame($peeked->value, $token->value);
    }


    public function testNextSkipsWhitespace(): void
    {
        $lexer = new BisonLexer("   \t\n\r  foo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsLineComment(): void
    {
        $lexer = new BisonLexer("// this is a comment\nfoo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsLineCommentAtEof(): void
    {
        $lexer = new BisonLexer('// comment without newline');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
    }

    public function testNextSkipsBlockComment(): void
    {
        $lexer = new BisonLexer('/* block comment */ foo');

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
        $lexer = new BisonLexer($input);

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsBlockCommentUnterminated(): void
    {
        $lexer = new BisonLexer('/* unterminated');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Eof, $token->type);
    }

    public function testNextSkipsConsecutiveLineComments(): void
    {
        $lexer = new BisonLexer("// first\n// second\nfoo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsConsecutiveBlockComments(): void
    {
        $lexer = new BisonLexer('/* first */ /* second */ foo');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }

    public function testNextSkipsMixedComments(): void
    {
        $lexer = new BisonLexer("/* block */ // line\nfoo");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo', $token->value);
    }


    public function testNextIdentifier(): void
    {
        $lexer = new BisonLexer('simple_ident');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('simple_ident', $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testNextIdentifierSingleChar(): void
    {
        $lexer = new BisonLexer('a');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('a', $token->value);
    }

    public function testNextIdentifierWithDigits(): void
    {
        $lexer = new BisonLexer('foo123');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('foo123', $token->value);
    }

    public function testNextIdentifierWithDots(): void
    {
        $lexer = new BisonLexer('api.pure.full');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('api.pure.full', $token->value);
    }

    public function testNextIdentifierWithUnderscore(): void
    {
        $lexer = new BisonLexer('_private');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('_private', $token->value);
    }

    public function testNextIdentifierUpperCase(): void
    {
        $lexer = new BisonLexer('SELECT_SYM');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Identifier, $token->type);
        self::assertSame('SELECT_SYM', $token->value);
    }


    public function testNextNumber(): void
    {
        $lexer = new BisonLexer('12345');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Number, $token->type);
        self::assertSame(12345, $token->value);
        self::assertSame(0, $token->offset);
    }

    public function testNextNumberSingleDigit(): void
    {
        $lexer = new BisonLexer('7');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Number, $token->type);
        self::assertSame(7, $token->value);
    }

    public function testNextNumberZero(): void
    {
        $lexer = new BisonLexer('0');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Number, $token->type);
        self::assertSame(0, $token->value);
    }


    public function testNextStringLiteral(): void
    {
        $lexer = new BisonLexer('"hello world"');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('hello world', $token->value);
    }

    public function testNextStringLiteralEmpty(): void
    {
        $lexer = new BisonLexer('""');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextStringLiteralWithEscapedQuote(): void
    {
        $lexer = new BisonLexer('"say \"hi\""');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('say "hi"', $token->value);
    }

    public function testNextStringLiteralWithBackslash(): void
    {
        $lexer = new BisonLexer('"path\\\\to\\\\file"');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('path\\to\\file', $token->value);
    }

    public function testNextStringLiteralUnterminated(): void
    {
        $lexer = new BisonLexer('"unterminated');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('unterminated', $token->value);
    }

    public function testNextStringLiteralEscapeAtEnd(): void
    {
        $lexer = new BisonLexer('"end\\');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::StringLiteral, $token->type);
        self::assertSame('end', $token->value);
    }


    public function testNextCharLiteral(): void
    {
        $lexer = new BisonLexer("'c'");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('c', $token->value);
    }

    public function testNextCharLiteralEmpty(): void
    {
        $lexer = new BisonLexer("''");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextCharLiteralWithEscape(): void
    {
        $lexer = new BisonLexer("'\\n'");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('n', $token->value);
    }

    public function testNextCharLiteralUnterminated(): void
    {
        $lexer = new BisonLexer("'x");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::CharLiteral, $token->type);
        self::assertSame('x', $token->value);
    }


    public function testNextColon(): void
    {
        $lexer = new BisonLexer(':');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Colon, $token->type);
        self::assertSame(':', $token->value);
    }

    public function testNextSemicolon(): void
    {
        $lexer = new BisonLexer(';');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Semicolon, $token->type);
        self::assertSame(';', $token->value);
    }

    public function testNextPipe(): void
    {
        $lexer = new BisonLexer('|');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Pipe, $token->type);
        self::assertSame('|', $token->value);
    }

    public function testNextPercentPercent(): void
    {
        $lexer = new BisonLexer('%%');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::PercentPercent, $token->type);
        self::assertSame('%%', $token->value);
    }


    public function testNextDirective(): void
    {
        $lexer = new BisonLexer('%token');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%token', $token->value);
    }

    public function testNextDirectiveWithHyphen(): void
    {
        $lexer = new BisonLexer('%parse-param');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%parse-param', $token->value);
    }

    public function testNextDirectiveWithNumbers(): void
    {
        $lexer = new BisonLexer('%define123');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%define123', $token->value);
    }

    public function testNextDirectiveWithDots(): void
    {
        $lexer = new BisonLexer('%api.pure');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%api.pure', $token->value);
    }


    public function testNextTypeTag(): void
    {
        $lexer = new BisonLexer('<node_type>');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('node_type', $token->value);
    }

    public function testNextTypeTagEmpty(): void
    {
        $lexer = new BisonLexer('<>');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextTypeTagTrimmed(): void
    {
        $lexer = new BisonLexer('<  spaced  >');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('spaced', $token->value);
    }

    public function testNextTypeTagWithSpecialChars(): void
    {
        $lexer = new BisonLexer('<Item*>');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::TypeTag, $token->type);
        self::assertSame('Item*', $token->value);
    }


    public function testNextPrologue(): void
    {
        $lexer = new BisonLexer('%{ #include <stdio.h> %}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame(' #include <stdio.h> ', $token->value);
    }

    public function testNextPrologueEmpty(): void
    {
        $lexer = new BisonLexer('%{%}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextPrologueWithWhitespaceOnly(): void
    {
        $lexer = new BisonLexer('%{   %}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame('   ', $token->value);
    }

    public function testNextPrologueUnterminated(): void
    {
        $lexer = new BisonLexer('%{ incomplete prologue');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame(' incomplete prologue', $token->value);
    }


    public function testNextAction(): void
    {
        $lexer = new BisonLexer('{ $$ = $1; }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' $$ = $1; ', $token->value);
    }

    public function testNextActionEmpty(): void
    {
        $lexer = new BisonLexer('{}');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame('', $token->value);
    }

    public function testNextActionWithWhitespaceOnly(): void
    {
        $lexer = new BisonLexer('{   }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame('   ', $token->value);
    }

    public function testNextActionNested(): void
    {
        $lexer = new BisonLexer('{ if (x) { y(); } }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' if (x) { y(); } ', $token->value);
    }

    public function testNextActionDeeplyNested(): void
    {
        $lexer = new BisonLexer('{ a { b { c } d } e }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' a { b { c } d } e ', $token->value);
    }

    public function testNextActionWithStringContainingBrace(): void
    {
        $lexer = new BisonLexer('{ printf("{"); }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' printf("{"); ', $token->value);
    }

    public function testNextActionWithCharLiteralContainingBrace(): void
    {
        $lexer = new BisonLexer("{ char c = '{'; }");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(" char c = '{'; ", $token->value);
    }

    public function testNextActionWithLineComment(): void
    {
        $lexer = new BisonLexer("{ x = 1; // comment\n y = 2; }");

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertIsString($token->value);
        self::assertStringContainsString('x = 1;', $token->value);
        self::assertStringContainsString('y = 2;', $token->value);
    }

    public function testNextActionWithBlockComment(): void
    {
        $lexer = new BisonLexer('{ x = 1; /* { not a brace } */ y = 2; }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertIsString($token->value);
        self::assertStringContainsString('x = 1;', $token->value);
        self::assertStringContainsString('y = 2;', $token->value);
    }

    public function testNextActionUnterminated(): void
    {
        $lexer = new BisonLexer('{ incomplete action');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        self::assertSame(' incomplete action', $token->value);
    }

    public function testNextActionWithUnterminatedString(): void
    {
        $lexer = new BisonLexer('{ "unterminated }');

        $token = $lexer->next();

        self::assertSame(BisonTokenType::Action, $token->type);
        // The string consumes to EOF, so action also consumes everything
        self::assertIsString($token->value);
    }


    public function testNextMultipleTokens(): void
    {
        $lexer = new BisonLexer('%token FOO 123 "alias"');

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
        $lexer = new BisonLexer('foo  bar');

        $t1 = $lexer->next();
        $t2 = $lexer->next();

        self::assertSame(0, $t1->offset);
        self::assertSame(5, $t2->offset);
    }

    public function testNextTracksOffsetAfterWhitespace(): void
    {
        $lexer = new BisonLexer('   foo');

        $token = $lexer->next();

        self::assertSame(3, $token->offset);
    }

    public function testNextTracksOffsetAfterComment(): void
    {
        $lexer = new BisonLexer('/* x */ foo');

        $token = $lexer->next();

        self::assertSame(8, $token->offset);
    }


    public function testNextThrowsForUnexpectedCharacter(): void
    {
        $lexer = new BisonLexer('@');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unexpected character '@' at offset 0");

        $lexer->next();
    }

    public function testNextThrowsForUnexpectedSlash(): void
    {
        $lexer = new BisonLexer('/ not a comment');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unexpected '/' at offset 0");

        $lexer->next();
    }

    public function testNextThrowsForEmptyDirective(): void
    {
        $lexer = new BisonLexer('% ');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Unexpected '%' at offset 0");

        $lexer->next();
    }

    public function testNextThrowsForUnterminatedTypeTag(): void
    {
        $lexer = new BisonLexer('<unterminated');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unterminated type tag starting at offset 0');

        $lexer->next();
    }

    public function testNextThrowsForDigitStartingIdentifierPosition(): void
    {
        // 数字で始まる場合はNumberとして処理され、後続は別トークン
        $lexer = new BisonLexer('123abc');

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

        $lexer = new BisonLexer($input);
        $tokens = [];

        while (true) {
            $token = $lexer->next();
            $tokens[] = $token;
            if ($token->type === BisonTokenType::Eof) {
                break;
            }
        }

        $types = array_map(fn ($t) => $t->type, $tokens);

        self::assertContains(BisonTokenType::Prologue, $types);
        self::assertContains(BisonTokenType::Directive, $types);
        self::assertContains(BisonTokenType::TypeTag, $types);
        self::assertContains(BisonTokenType::Number, $types);
        self::assertContains(BisonTokenType::PercentPercent, $types);
        self::assertContains(BisonTokenType::Identifier, $types);
        self::assertContains(BisonTokenType::Colon, $types);
        self::assertContains(BisonTokenType::Pipe, $types);
        self::assertContains(BisonTokenType::Action, $types);
        self::assertContains(BisonTokenType::Semicolon, $types);
        self::assertContains(BisonTokenType::Eof, $types);
    }

    #[DataProvider('providerNextTokenTypes')]
    public function testNextTokenTypes(string $input, BisonTokenType $expectedType, string|int $expectedValue): void
    {
        $lexer = new BisonLexer($input);

        $token = $lexer->next();

        self::assertSame($expectedType, $token->type);
        self::assertSame($expectedValue, $token->value);
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


    public function testPeekReturnsSameToken(): void
    {
        $lexer = new BisonLexer('identifier');

        $first = $lexer->peek();
        $second = $lexer->peek();

        self::assertSame($first->type, $second->type);
        self::assertSame($first->value, $second->value);
        self::assertSame($first->offset, $second->offset);
    }

    public function testPeekDoesNotConsume(): void
    {
        $lexer = new BisonLexer('foo bar');

        $peeked = $lexer->peek();
        $next = $lexer->next();

        self::assertSame($peeked->value, $next->value);
        self::assertSame('foo', $next->value);
    }

    public function testPeekThenNextThenPeek(): void
    {
        $lexer = new BisonLexer('foo bar baz');

        $peek1 = $lexer->peek();
        self::assertSame('foo', $peek1->value);

        $next1 = $lexer->next();
        self::assertSame('foo', $next1->value);

        $peek2 = $lexer->peek();
        self::assertSame('bar', $peek2->value);
    }


    public function testPeekNReturnsNthToken(): void
    {
        $lexer = new BisonLexer('foo bar baz');

        $first = $lexer->peekN(1);
        $second = $lexer->peekN(2);
        $third = $lexer->peekN(3);

        self::assertSame('foo', $first->value);
        self::assertSame('bar', $second->value);
        self::assertSame('baz', $third->value);
    }

    public function testPeekNReturnsSameResult(): void
    {
        $lexer = new BisonLexer('a b c');

        $first1 = $lexer->peekN(1);
        $second1 = $lexer->peekN(2);
        $first2 = $lexer->peekN(1);
        $second2 = $lexer->peekN(2);

        self::assertSame($first1->value, $first2->value);
        self::assertSame($second1->value, $second2->value);
    }

    public function testPeekNAfterPartialConsumption(): void
    {
        $lexer = new BisonLexer('a b c d');

        // Fill buffer with 3 tokens
        $lexer->peekN(3);

        // Consume first token
        $lexer->next();

        // peekN(1) should return 'b' from buffer
        $peek1 = $lexer->peekN(1);
        self::assertSame('b', $peek1->value);

        // peekN(2) should return 'c' from buffer
        $peek2 = $lexer->peekN(2);
        self::assertSame('c', $peek2->value);
    }

    public function testPeekNThenPeekNSmaller(): void
    {
        $lexer = new BisonLexer('a b c');

        // First peek 3 tokens ahead
        $third = $lexer->peekN(3);
        self::assertSame('c', $third->value);

        // Then peek 1 token - should return from buffer
        $first = $lexer->peekN(1);
        self::assertSame('a', $first->value);
    }

    public function testPeekNThrowsForZero(): void
    {
        $lexer = new BisonLexer('foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('peekN($n) requires $n >= 1');

        $lexer->peekN(0);
    }

    public function testPeekNThrowsForNegative(): void
    {
        $lexer = new BisonLexer('foo');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('peekN($n) requires $n >= 1');

        $lexer->peekN(-1);
    }
}
