<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Ast\Location;
use BisonParser\Scanner\CodeReader;
use BisonParser\Scanner\Cursor;
use BisonParser\Scanner\Directives;
use BisonParser\Scanner\Escapes;
use BisonParser\Scanner\Scanner;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scanner::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Directives::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[Small]
final class ScannerTest extends TestCase
{
    public function testScan(): void
    {
        $tokens = (new Scanner())->scan("%token NUM 258 \"number\"\n%%\nexpr[e]: expr '+' NUM { \$\$ = \$1 + \$3; } | NUM ;\n%%\nint main() {}\n");

        self::assertSame(
            [
                [TokenKind::Directive, 'token', '1:1'],
                [TokenKind::Identifier, 'NUM', '1:8'],
                [TokenKind::Integer, '258', '1:12'],
                [TokenKind::String, 'number', '1:16'],
                [TokenKind::Section, '%%', '2:1'],
                [TokenKind::IdentifierColon, 'expr', '3:1'],
                [TokenKind::BracketedIdentifier, 'e', '3:5'],
                [TokenKind::Colon, ':', '3:8'],
                [TokenKind::Identifier, 'expr', '3:10'],
                [TokenKind::CharLiteral, '+', '3:15'],
                [TokenKind::Identifier, 'NUM', '3:19'],
                [TokenKind::Code, ' $$ = $1 + $3; ', '3:23'],
                [TokenKind::Pipe, '|', '3:41'],
                [TokenKind::Identifier, 'NUM', '3:43'],
                [TokenKind::Semicolon, ';', '3:47'],
                [TokenKind::Section, '%%', '4:1'],
                [TokenKind::Epilogue, "\nint main() {}\n", '4:3'],
                [TokenKind::End, '', '6:1'],
            ],
            array_map(static fn (Token $token): array => [$token->kind, $token->text, (string) $token->location], $tokens),
        );
    }

    public function testScanEndsWithoutAnEpilogue(): void
    {
        $tokens = (new Scanner())->scan('%% a: ;');

        self::assertSame([TokenKind::Section, TokenKind::IdentifierColon, TokenKind::Colon, TokenKind::Semicolon, TokenKind::End], array_map(static fn (Token $token): TokenKind => $token->kind, $tokens));
    }

    public function testSkipTrivia(): void
    {
        $scanner = new Scanner();
        $cursor = new Cursor("  , // line\n\t/* block\n */\r\n#line 12 \"x.y\"\nnext");

        $scanner->skipTrivia($cursor);

        self::assertSame('next', $cursor->take(4));
        self::assertTrue($cursor->eof());
    }

    public function testSkipTriviaRejectsAnUnterminatedComment(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '*/' closing the comment opened at 1:2");

        (new Scanner())->skipTrivia(new Cursor(' /* open'));
    }

    public function testNext(): void
    {
        $scanner = new Scanner();

        self::assertSame(TokenKind::Directive, $scanner->next(new Cursor('%left'))[0]->kind);
        self::assertSame(TokenKind::Code, $scanner->next(new Cursor('{ x }'))[0]->kind);
        self::assertSame(TokenKind::Identifier, $scanner->next(new Cursor('.opt'))[0]->kind);
        self::assertSame(TokenKind::Integer, $scanner->next(new Cursor('42'))[0]->kind);
        self::assertSame(TokenKind::TranslatableString, $scanner->next(new Cursor('_("x")'))[0]->kind);
        self::assertSame(TokenKind::Tag, $scanner->next(new Cursor('<int>'))[0]->kind);
        self::assertSame(TokenKind::Pipe, $scanner->next(new Cursor('|'))[0]->kind);
    }

    public function testNextRejectsAnInvalidCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Invalid character: '@' at 1:1");

        (new Scanner())->next(new Cursor('@'));
    }

    public function testPercent(): void
    {
        $scanner = new Scanner();
        $prologue = $scanner->percent(new Cursor('%{ code %}'), new Location(1, 1));
        $section = $scanner->percent(new Cursor('%%'), new Location(2, 1));
        $predicate = $scanner->percent(new Cursor("%?  \n {ok}"), new Location(3, 1));
        $spelled = $scanner->percent(new Cursor('%pure_parser'), new Location(4, 1));
        $cursor = new Cursor('%name-prefix = "yy"');
        $option = $scanner->percent($cursor, new Location(5, 1));

        self::assertSame([TokenKind::Prologue, ' code '], [$prologue->kind, $prologue->text]);
        self::assertSame(TokenKind::Section, $section->kind);
        self::assertSame([TokenKind::Predicate, 'ok'], [$predicate->kind, $predicate->text]);
        self::assertSame(['pure-parser', '%pure_parser'], [$spelled->text, $spelled->raw]);
        self::assertSame('name-prefix', $option->text);
        self::assertSame('"yy"', $cursor->take(4));
    }

    public function testPercentRejectsAnUnknownDirective(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid directive: %tokens at 1:1');

        (new Scanner())->percent(new Cursor('%tokens'), new Location(1, 1));
    }

    public function testPercentRejectsABarePercentSign(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid directive: % at 1:1');

        (new Scanner())->percent(new Cursor('% '), new Location(1, 1));
    }

    public function testIdentifier(): void
    {
        $scanner = new Scanner();
        $plain = $scanner->identifier(new Cursor('expr.opt rest'), new Location(1, 1));
        $lhs = $scanner->identifier(new Cursor("expr /* c */ [e] \n:"), new Location(2, 1));
        $referenced = $scanner->identifier(new Cursor('expr[e] NUM'), new Location(3, 1));

        self::assertSame([TokenKind::Identifier, 'expr.opt'], [$plain[0]->kind, $plain[0]->text]);
        self::assertCount(1, $plain);
        self::assertSame([TokenKind::IdentifierColon, 'expr'], [$lhs[0]->kind, $lhs[0]->text]);
        self::assertSame([TokenKind::BracketedIdentifier, 'e', '1:14'], [$lhs[1]->kind, $lhs[1]->text, (string) $lhs[1]->location]);
        self::assertSame([TokenKind::Identifier, TokenKind::BracketedIdentifier], [$referenced[0]->kind, $referenced[1]->kind]);
    }

    public function testBracketed(): void
    {
        $cursor = new Cursor('[ name ] rest');

        $token = (new Scanner())->bracketed($cursor);

        self::assertSame([TokenKind::BracketedIdentifier, 'name', '1:1'], [$token->kind, $token->text, (string) $token->location]);
        self::assertSame(' rest', $cursor->take(5));
    }

    public function testBracketedRejectsAMissingName(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('An identifier expected at 1:2');

        (new Scanner())->bracketed(new Cursor('[1]'));
    }

    public function testBracketedRejectsAMissingCloser(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected ']' but found ';' at 1:6");

        (new Scanner())->bracketed(new Cursor('[name;'));
    }

    public function testBracketedRejectsTheEndOfTheFile(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected ']' but found end of file at 1:6");

        (new Scanner())->bracketed(new Cursor('[name'));
    }

    public function testInteger(): void
    {
        $scanner = new Scanner();
        $decimal = $scanner->integer(new Cursor('258 '), new Location(1, 1));
        $hex = $scanner->integer(new Cursor('0x1F;'), new Location(1, 5));

        self::assertSame([TokenKind::Integer, '258', '258'], [$decimal->kind, $decimal->text, $decimal->raw]);
        self::assertSame(['31', '0x1F'], [$hex->text, $hex->raw]);
    }

    public function testIntegerRejectsAnIdentifierStartingWithADigit(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Invalid identifier: 1st_rule at 1:1');

        (new Scanner())->integer(new Cursor('1st_rule:'), new Location(1, 1));
    }

    public function testLiteral(): void
    {
        $scanner = new Scanner();
        $char = $scanner->literal(new Cursor("'\\n'"), new Location(1, 1));
        $string = $scanner->literal(new Cursor('"a\"b"'), new Location(1, 1));
        $translatable = $scanner->literal(new Cursor('_("number")'), new Location(1, 1));
        $tag = $scanner->literal(new Cursor('<int>'), new Location(1, 1));

        self::assertSame([TokenKind::CharLiteral, "\n"], [$char?->kind, $char?->text]);
        self::assertSame([TokenKind::String, 'a"b'], [$string?->kind, $string?->text]);
        self::assertSame([TokenKind::TranslatableString, 'number'], [$translatable?->kind, $translatable?->text]);
        self::assertSame([TokenKind::Tag, 'int'], [$tag?->kind, $tag?->text]);
        self::assertNull($scanner->literal(new Cursor(':'), new Location(1, 1)));
    }

    public function testLiteralRejectsAnUnterminatedCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing ''' closing the character literal opened at 1:1");

        (new Scanner())->literal(new Cursor("'a\n'"), new Location(1, 1));
    }

    public function testLiteralRejectsAnEmptyCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Empty character literal at 1:1');

        (new Scanner())->literal(new Cursor("''"), new Location(1, 1));
    }

    public function testLiteralRejectsAWideCharacter(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Extra characters in character literal at 1:1');

        (new Scanner())->literal(new Cursor("'ab'"), new Location(1, 1));
    }

    public function testLiteralRejectsAnUnterminatedString(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '\"' closing the string opened at 1:1");

        (new Scanner())->literal(new Cursor("\"abc\n"), new Location(1, 1));
    }

    public function testTag(): void
    {
        $scanner = new Scanner();
        $any = $scanner->tag(new Cursor('<*>'), new Location(1, 1));
        $none = $scanner->tag(new Cursor('<>'), new Location(1, 1));
        $nested = $scanner->tag(new Cursor('<std::map<int, std::vector<x>>*>'), new Location(1, 1));
        $arrow = $scanner->tag(new Cursor('<a->b>'), new Location(1, 1));

        self::assertSame([TokenKind::TagAny, '*'], [$any->kind, $any->text]);
        self::assertSame([TokenKind::TagNone, ''], [$none->kind, $none->text]);
        self::assertSame([TokenKind::Tag, 'std::map<int, std::vector<x>>*'], [$nested->kind, $nested->text]);
        self::assertSame('a->b', $arrow->text);
    }

    public function testTagRejectsAnUnterminatedTag(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Missing '>' closing the tag opened at 1:1");

        (new Scanner())->tag(new Cursor('<int'), new Location(1, 1));
    }

    public function testPunctuation(): void
    {
        $scanner = new Scanner();

        self::assertSame(TokenKind::Colon, $scanner->punctuation(new Cursor(':'), new Location(1, 1))?->kind);
        self::assertSame(TokenKind::Equal, $scanner->punctuation(new Cursor('='), new Location(1, 1))?->kind);
        self::assertSame(TokenKind::Pipe, $scanner->punctuation(new Cursor('|'), new Location(1, 1))?->kind);
        self::assertSame(TokenKind::Semicolon, $scanner->punctuation(new Cursor(';'), new Location(1, 1))?->kind);
        self::assertSame(TokenKind::BracketedIdentifier, $scanner->punctuation(new Cursor('[x]'), new Location(1, 1))?->kind);
        self::assertNull($scanner->punctuation(new Cursor('@'), new Location(1, 1)));
    }
}
