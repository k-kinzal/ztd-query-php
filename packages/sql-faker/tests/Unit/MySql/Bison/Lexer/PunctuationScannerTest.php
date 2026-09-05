<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use SqlFaker\MySql\Bison\Lexer\PunctuationScanner;

#[CoversClass(PunctuationScanner::class)]
#[UsesClass(BisonTokenType::class)]
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
    public function testScanReadsEachPunctuationCharacterAsItsOwnLexeme(string $character, BisonTokenType $expected): void
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
     * @return iterable<string, array{string, BisonTokenType}>
     */
    public static function providerPunctuation(): iterable
    {
        yield 'colon opens the alternatives' => [':', BisonTokenType::Colon];
        yield 'semicolon closes the rule' => [';', BisonTokenType::Semicolon];
        yield 'pipe separates alternatives' => ['|', BisonTokenType::Pipe];
        yield 'equals' => ['=', BisonTokenType::CharLiteral];
        yield 'comma' => [',', BisonTokenType::CharLiteral];
        yield 'open paren' => ['(', BisonTokenType::CharLiteral];
        yield 'close paren' => [')', BisonTokenType::CharLiteral];
        yield 'open bracket' => ['[', BisonTokenType::CharLiteral];
        yield 'close bracket' => [']', BisonTokenType::CharLiteral];
    }
}
