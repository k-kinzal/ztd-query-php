<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\StringLiteral as Subject;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class StringLiteralTest extends TestCase
{
    #[DataProvider('providerLiterals')]
    public function testDecodeReadsTheTextALiteralDenotes(string $sql, string $expected): void
    {
        $tokens = (new MySqlParser())->tokenize($sql);
        $literal = $tokens[1]->is('UNDERSCORE_CHARSET') ? $tokens[2] : $tokens[1];

        self::assertSame($expected, (new Subject())->decode($literal));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerLiterals(): array
    {
        return [
            ["SELECT 'plain'", 'plain'],
            ["SELECT 'a''b'", "a'b"],
            ["SELECT 'a\\'b'", "a'b"],
            ['SELECT "d""q"', 'd"q'],
            ['SELECT "d\\"q"', 'd"q'],
            ["SELECT N'national'", 'national'],
            ["SELECT _utf8mb4'intro'", 'intro'],
            ["SELECT 'tab\\there'", "tab\there"],
            ["SELECT 'new\\nline'", "new\nline"],
            ["SELECT 'back\\\\slash'", 'back\\slash'],
            ["SELECT 'per\\%cent'", 'per\\%cent'],
            ["SELECT 'under\\_score'", 'under\\_score'],
            ["SELECT 'null\\0byte'", "null\0byte"],
            ["SELECT 'ret\\rturn'", "ret\rturn"],
            ["SELECT 'bell\\bx'", "bell\x08x"],
            ["SELECT 'eof\\Zx'", "eof\x1ax"],
            ["SELECT 'other\\qx'", 'otherqx'],
            ["SELECT ''", ''],
        ];
    }

    public function testDecodeLeavesUnquotedTokensAlone(): void
    {
        self::assertSame('x', (new Subject())->decode(new Token(1, 'TEXT_STRING', 'x', 0)));
        self::assertSame("'", (new Subject())->decode(new Token(1, 'TEXT_STRING', "'", 0)));
    }

    public function testUnescapeKeepsATrailingBackslash(): void
    {
        self::assertSame('end\\', (new Subject())->unescape('end\\', "'"));
        self::assertSame("it's", (new Subject())->unescape("it''s", "'"));
        self::assertSame("it''s", (new Subject())->unescape("it''s", '"'));
    }

    public function testBytesReadsHexadecimalAndBinaryLiterals(): void
    {
        $tokens = (new MySqlParser())->tokenize("SELECT 0x4142, x'41', b'01000001', 0b01000001");

        self::assertSame('AB', (new Subject())->bytes($tokens[1]));
        self::assertSame('A', (new Subject())->bytes($tokens[3]));
        self::assertSame('A', (new Subject())->bytes($tokens[5]));
        self::assertSame('A', (new Subject())->bytes($tokens[7]));
    }

    public function testBytesPadsAnIncompleteHexadecimalOrBinaryRun(): void
    {
        self::assertSame("\x0a", (new Subject())->bytes(new Token(1, 'HEX_NUM', '0xA', 0)));
        self::assertSame("\x05", (new Subject())->bytes(new Token(1, 'BIN_NUM', "b'101'", 0)));
        self::assertSame("\0", (new Subject())->bytes(new Token(1, 'BIN_NUM', "b''", 0)));
    }
}
