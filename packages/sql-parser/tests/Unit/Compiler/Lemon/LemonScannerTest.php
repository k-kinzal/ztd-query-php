<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonScanner;
use SqlParser\Compiler\Lemon\LemonToken;
use SqlParser\Compiler\Lemon\LemonTokenKind;
use SqlParser\Compiler\SourceReader;

#[CoversClass(LemonScanner::class)]
#[UsesClass(CodeBlockReader::class)]
#[UsesClass(GrammarSourceException::class)]
#[UsesClass(LemonToken::class)]
#[UsesClass(SourceReader::class)]
#[Small]
final class LemonScannerTest extends TestCase
{
    public function testScan(): void
    {
        $source = "%include { int x; } // c\n/* c */ expr(A) ::= expr(B) PLUS|MINUS expr. [PLUS] { act(); } %name \"p\" 12";
        $tokens = (new LemonScanner())->scan($source);
        $kinds = array_map(static fn (LemonToken $token): string => $token->kind->name, $tokens);

        self::assertSame(['Directive', 'Code', 'Identifier', 'Alias', 'Arrow', 'Identifier', 'Alias', 'Identifier', 'Pipe', 'Identifier', 'Identifier', 'Dot', 'PrecedenceMark', 'Code', 'Directive', 'String', 'Number'], $kinds);
        self::assertSame('include', $tokens[0]->text);
        self::assertSame('A', $tokens[3]->text);
        self::assertSame('PLUS', $tokens[12]->text);
        self::assertSame('p', $tokens[15]->text);
        self::assertSame(2, $tokens[2]->line);
    }

    public function testNext(): void
    {
        $scanner = new LemonScanner();

        self::assertNull($scanner->next(new SourceReader('  ')));
        self::assertNull($scanner->next(new SourceReader('// note')));
        self::assertNull($scanner->next(new SourceReader('/* note */')));
        self::assertSame(LemonTokenKind::Other, $scanner->next(new SourceReader('='))?->kind);
    }

    public function testNextRejectsAnUnterminatedComment(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonScanner())->next(new SourceReader('/* open'));
    }

    public function testBracketed(): void
    {
        $scanner = new LemonScanner();

        self::assertSame('{ x }', $scanner->bracketed(new SourceReader('{ x }'), 1)?->text);
        self::assertSame(LemonTokenKind::Alias, $scanner->bracketed(new SourceReader('( A )'), 1)?->kind);
        self::assertSame('NEG', $scanner->bracketed(new SourceReader('[NEG]'), 1)?->text);
        self::assertSame('a\\"b', $scanner->bracketed(new SourceReader('"a\\"b"'), 1)?->text);
        self::assertNull($scanner->bracketed(new SourceReader('x'), 1));
    }

    public function testBracketedRejectsAMalformedAlias(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonScanner())->bracketed(new SourceReader('(1)'), 1);
    }

    public function testBracketedRejectsAnUnterminatedString(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonScanner())->bracketed(new SourceReader('"open'), 1);
    }

    public function testWord(): void
    {
        $scanner = new LemonScanner();

        self::assertSame('token_class', $scanner->word(new SourceReader('%token_class id'), 1)?->text);
        self::assertSame(LemonTokenKind::Identifier, $scanner->word(new SourceReader('nm'), 1)?->kind);
        self::assertSame(LemonTokenKind::Number, $scanner->word(new SourceReader('42'), 1)?->kind);
        self::assertNull($scanner->word(new SourceReader('.'), 1));
    }

    public function testPunctuation(): void
    {
        $scanner = new LemonScanner();

        self::assertSame(LemonTokenKind::Arrow, $scanner->punctuation(new SourceReader('::='), 1)->kind);
        self::assertSame(LemonTokenKind::Dot, $scanner->punctuation(new SourceReader('.'), 1)->kind);
        self::assertSame(LemonTokenKind::Pipe, $scanner->punctuation(new SourceReader('|'), 1)->kind);
        self::assertSame(LemonTokenKind::Other, $scanner->punctuation(new SourceReader('='), 1)->kind);
    }
}
