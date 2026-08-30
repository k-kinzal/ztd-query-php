<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonLexeme;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonToken;
use SqlFaker\Grammar\Source\Bison\Lexer\PunctuationScanner;
use SqlFaker\Grammar\Source\SourceCursor;

#[CoversClass(PunctuationScanner::class)]
#[UsesClass(BisonLexeme::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(SourceCursor::class)]
final class PunctuationScannerTest extends TestCase
{
    public function testHandlesRejectsCharactersItDoesNotOwn(): void
    {
        $scanner = new PunctuationScanner();

        self::assertFalse($scanner->handles('@'));
        self::assertFalse($scanner->handles('a'));
        self::assertFalse($scanner->handles('%'));
    }

    #[DataProvider('providerPunctuation')]
    public function testScanReadsEachPunctuationCharacterAsItsOwnLexeme(string $character, BisonLexeme $expected): void
    {
        $scanner = new PunctuationScanner();
        $cursor = new SourceCursor($character . 'rest');

        self::assertTrue($scanner->handles($character));

        $token = $scanner->scan($cursor);

        self::assertSame($expected, $token->type);
        self::assertSame($character, $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame('rest', $cursor->takeRest());
    }

    /**
     * @return iterable<string, array{string, BisonLexeme}>
     */
    public static function providerPunctuation(): iterable
    {
        yield 'colon opens the alternatives' => [':', BisonLexeme::Colon];
        yield 'semicolon closes the rule' => [';', BisonLexeme::Semicolon];
        yield 'pipe separates alternatives' => ['|', BisonLexeme::Pipe];
        yield 'equals' => ['=', BisonLexeme::CharLiteral];
        yield 'comma' => [',', BisonLexeme::CharLiteral];
        yield 'open paren' => ['(', BisonLexeme::CharLiteral];
        yield 'close paren' => [')', BisonLexeme::CharLiteral];
        yield 'open bracket' => ['[', BisonLexeme::CharLiteral];
        yield 'close bracket' => [']', BisonLexeme::CharLiteral];
    }
}
