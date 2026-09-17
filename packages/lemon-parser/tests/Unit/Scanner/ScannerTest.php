<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use LemonParser\Ast\Location;
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\Scanner\Scanner;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scanner::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class ScannerTest extends TestCase
{
    public function testScan(): void
    {
        $tokens = (new Scanner())->scan("%name \"Calc\" // c\nexpr(A) ::= expr(B) PLUS|MINUS 1x. [PLUS] { A = B; }\n");

        self::assertSame(
            [
                [TokenKind::Punctuation, '%', '1:1'],
                [TokenKind::Word, 'name', '1:2'],
                [TokenKind::String, 'Calc', '1:7'],
                [TokenKind::Word, 'expr', '2:1'],
                [TokenKind::Punctuation, '(', '2:5'],
                [TokenKind::Word, 'A', '2:6'],
                [TokenKind::Punctuation, ')', '2:7'],
                [TokenKind::Arrow, '::=', '2:9'],
                [TokenKind::Word, 'expr', '2:13'],
                [TokenKind::Punctuation, '(', '2:17'],
                [TokenKind::Word, 'B', '2:18'],
                [TokenKind::Punctuation, ')', '2:19'],
                [TokenKind::Word, 'PLUS', '2:21'],
                [TokenKind::Compound, 'MINUS', '2:25'],
                [TokenKind::Word, '1x', '2:32'],
                [TokenKind::Punctuation, '.', '2:34'],
                [TokenKind::Punctuation, '[', '2:36'],
                [TokenKind::Word, 'PLUS', '2:37'],
                [TokenKind::Punctuation, ']', '2:41'],
                [TokenKind::Code, ' A = B; ', '2:43'],
                [TokenKind::End, '', '3:1'],
            ],
            array_map(static fn (Token $token): array => [$token->kind, $token->text, (string) $token->location], $tokens),
        );
    }

    public function testSkipTrivia(): void
    {
        $scanner = new Scanner();
        $cursor = new Cursor(" \t// line\n/* block\n */ /*/ still */next");

        $scanner->skipTrivia($cursor);

        self::assertSame('next', $cursor->take(4));
        self::assertTrue($cursor->eof());
    }

    public function testSkipTriviaRunsAnOpenCommentToTheEnd(): void
    {
        $cursor = new Cursor('/* open');

        (new Scanner())->skipTrivia($cursor);

        self::assertTrue($cursor->eof());
    }

    public function testNext(): void
    {
        $scanner = new Scanner();
        $string = $scanner->next(new Cursor("\"a\nb\" x"));
        $code = $scanner->next(new Cursor('{ x } y'));
        $word = $scanner->next(new Cursor('a_1-'));
        $digits = $scanner->next(new Cursor('12ab.'));
        $arrow = $scanner->next(new Cursor('::= x'));
        $compound = $scanner->next(new Cursor('/Ab_c|x'));
        $bar = $scanner->next(new Cursor('| A'));
        $colon = $scanner->next(new Cursor(':: x'));

        self::assertSame([TokenKind::String, "a\nb", "\"a\nb\""], [$string->kind, $string->text, $string->raw]);
        self::assertSame([TokenKind::Code, ' x ', '{ x }'], [$code->kind, $code->text, $code->raw]);
        self::assertSame([TokenKind::Word, 'a_1'], [$word->kind, $word->text]);
        self::assertSame([TokenKind::Word, '12ab'], [$digits->kind, $digits->text]);
        self::assertSame(TokenKind::Arrow, $arrow->kind);
        self::assertSame([TokenKind::Compound, 'Ab_c', '/Ab_c'], [$compound->kind, $compound->text, $compound->raw]);
        self::assertSame([TokenKind::Punctuation, '|'], [$bar->kind, $bar->text]);
        self::assertSame([TokenKind::Punctuation, ':'], [$colon->kind, $colon->text]);
    }

    public function testNextRejectsAnUnterminatedString(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('String starting on this line is not terminated before the end of the file. at 1:1');

        (new Scanner())->next(new Cursor('"abc'));
    }
}
