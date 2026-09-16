<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonScanner;
use SqlParser\Compiler\Bison\BisonToken;
use SqlParser\Compiler\Bison\BisonTokenKind;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;

#[CoversClass(BisonScanner::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(SourceReader::class)]
#[Small]
final class BisonScannerTest extends TestCase
{
    public function testScanDropsPrologueCommentsAndEpilogue(): void
    {
        $source = "%{\n#include <x>\n%}\n/* c */ %token <str> IDENT 258 \"id\" // line\n%%\nstart: IDENT '(' { $$ = 1; } ;\n%%\nint main() {}\n";
        $tokens = (new BisonScanner())->scan($source);
        $kinds = array_map(static fn (BisonToken $token): string => $token->kind->name, $tokens);

        self::assertSame(['Directive', 'Tag', 'Identifier', 'Number', 'String', 'Section', 'Identifier', 'Colon', 'Identifier', 'CharLiteral', 'Code', 'Semicolon', 'Section'], $kinds);
        self::assertSame('token', $tokens[0]->text);
        self::assertSame('str', $tokens[1]->text);
        self::assertSame('(', $tokens[9]->text);
        self::assertSame(4, $tokens[0]->line);
    }

    public function testNext(): void
    {
        $scanner = new BisonScanner();

        self::assertNull($scanner->next(new SourceReader('   ')));
        self::assertSame(BisonTokenKind::Section, $scanner->next(new SourceReader('%%'))?->kind);
        self::assertSame('name-prefix', $scanner->next(new SourceReader('%name-prefix="x"'))?->text);
    }

    public function testNextRejectsAnUnterminatedPrologue(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new BisonScanner())->next(new SourceReader('%{ open'));
    }

    public function testSkipComment(): void
    {
        $scanner = new BisonScanner();
        $reader = new SourceReader('/* a */b');

        self::assertTrue($scanner->skipComment($reader));
        self::assertFalse($scanner->skipComment($reader));
        self::assertTrue($scanner->skipComment(new SourceReader("// x\n")));
    }

    public function testSkipCommentRejectsAnUnterminatedComment(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new BisonScanner())->skipComment(new SourceReader('/* open'));
    }

    public function testLiteral(): void
    {
        $scanner = new BisonScanner();

        self::assertSame("'", $scanner->literal(new SourceReader("'\\''"), 1)?->text);
        self::assertSame('{ x }', $scanner->literal(new SourceReader('{ x }'), 1)?->text);
        self::assertSame('a"b', $scanner->literal(new SourceReader('"a\\"b"'), 1)?->text);
        self::assertSame('lexer.keyword', $scanner->literal(new SourceReader('<lexer.keyword>'), 1)?->text);
        self::assertNull($scanner->literal(new SourceReader('x'), 1));
    }

    public function testLiteralRejectsAnUnterminatedCharacter(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new BisonScanner())->literal(new SourceReader("'ab"), 1);
    }

    public function testLiteralRejectsAnUnterminatedString(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new BisonScanner())->literal(new SourceReader('"ab'), 1);
    }

    public function testLiteralRejectsAnUnterminatedTag(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new BisonScanner())->literal(new SourceReader('<ab'), 1);
    }

    public function testWord(): void
    {
        $scanner = new BisonScanner();

        self::assertSame('select_item.list', $scanner->word(new SourceReader('select_item.list x'), 1)?->text);
        self::assertSame(BisonTokenKind::Number, $scanner->word(new SourceReader('258'), 1)?->kind);
        self::assertNull($scanner->word(new SourceReader(':'), 1));
    }

    public function testPunctuation(): void
    {
        $scanner = new BisonScanner();

        self::assertSame(BisonTokenKind::Colon, $scanner->punctuation(new SourceReader(':'), 1)->kind);
        self::assertSame(BisonTokenKind::Pipe, $scanner->punctuation(new SourceReader('|'), 1)->kind);
        self::assertSame(BisonTokenKind::Semicolon, $scanner->punctuation(new SourceReader(';'), 1)->kind);
        self::assertSame(BisonTokenKind::BracketOpen, $scanner->punctuation(new SourceReader('['), 1)->kind);
        self::assertSame(BisonTokenKind::BracketClose, $scanner->punctuation(new SourceReader(']'), 1)->kind);
        self::assertSame(BisonTokenKind::Other, $scanner->punctuation(new SourceReader('='), 1)->kind);
    }

    public function testUnescape(): void
    {
        self::assertSame("'\"\\\n\t\r\0", (new BisonScanner())->unescape("\\'\\\"\\\\\\n\\t\\r\\0"));
    }
}
