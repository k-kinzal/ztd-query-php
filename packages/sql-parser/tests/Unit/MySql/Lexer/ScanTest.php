<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Cursor;
use SqlParser\Lexer\Lexeme;
use SqlParser\MySql\Lexer\KeywordTable;
use SqlParser\MySql\Lexer\LexerState;
use SqlParser\MySql\Lexer\Scan;
use SqlParser\MySql\MySqlVersion;
use SqlParser\MySql\SqlMode;

#[CoversClass(Scan::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(KeywordTable::class)]
#[UsesClass(MySqlVersion::class)]
#[UsesClass(SqlMode::class)]
#[UsesClass(\SqlParser\Resource\SqlVersion::class)]
#[UsesClass(\SqlParser\Resource\VersionRegistry::class)]
#[Small]
final class ScanTest extends TestCase
{
    public function testLexeme(): void
    {
        $scan = new Scan(new Cursor('SELECT 1'), new KeywordTable([], []), new SqlMode(), MySqlVersion::resolve());
        $scan->cursor->take(6);
        $lexeme = $scan->lexeme('SELECT_SYM', 0);

        self::assertSame('SELECT', $lexeme->text);
        self::assertSame(0, $lexeme->offset);
        self::assertSame(LexerState::Start, $scan->next);
        self::assertFalse($scan->inVersionComment);
    }

    public function testIsIdentifierByte(): void
    {
        self::assertTrue(Scan::isIdentifierByte('a'));
        self::assertTrue(Scan::isIdentifierByte('9'));
        self::assertTrue(Scan::isIdentifierByte('_'));
        self::assertTrue(Scan::isIdentifierByte('$'));
        self::assertTrue(Scan::isIdentifierByte("\xC3"));
        self::assertFalse(Scan::isIdentifierByte('-'));
        self::assertFalse(Scan::isIdentifierByte(''));
    }
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerLatinBytes(): iterable
    {
        $letters = [0x83, 0x8A, 0x8C, 0x8E, 0x9A, 0x9C, 0x9E, 0x9F, ...range(0xC0, 0xD6), ...range(0xD8, 0xF6), ...range(0xF8, 0xFF)];
        foreach (range(128, 255) as $byte) {
            yield dechex($byte) => [chr($byte), in_array($byte, $letters, true)];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLatinBytes')]
    public function testIsIdentifierByteUsesTheObservedLatinLetterClasses(string $byte, bool $letter): void
    {
        self::assertSame($letter, Scan::isIdentifierByte($byte, 'latin1'));
        self::assertTrue(Scan::isIdentifierByte($byte, 'utf8mb4'));
    }

}
