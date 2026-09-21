<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\StringLiteral as Subject;
use SqlParser\Lexer\Token;
use SqlParser\PostgreSql\PostgreSqlParser;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Syntax\QuotedText::class)]
final class StringLiteralTest extends TestCase
{
    #[DataProvider('providerLiterals')]
    public function testDecodeReadsTheTextAConstantDenotes(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Subject())->decode((new PostgreSqlParser())->tokenize($sql)[1]));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerLiterals(): array
    {
        return [
            ["SELECT 'plain'", 'plain'],
            ["SELECT 'a''b'", "a'b"],
            ["SELECT ''", ''],
            ["SELECT E'x\\ny'", "x\ny"],
            ["SELECT e'tab\\tbell\\bform\\fret\\r'", "tab\tbell\x08form\x0cret\r"],
            ["SELECT E'back\\\\slash \\'q'", "back\\slash 'q"],
            ["SELECT E'other\\qx'", 'otherqx'],
            ['SELECT $$dollar$$', 'dollar'],
            ['SELECT $tag$in $$ side$tag$', 'in $$ side'],
            ['SELECT $$$$', ''],
            ["SELECT B'01'", "B'01'"],
        ];
    }

    public function testDecodeLeavesMalformedTokensAlone(): void
    {
        self::assertSame('$', (new Subject())->decode(new Token(1, 'SCONST', '$', 0)));
        self::assertSame('x', (new Subject())->decode(new Token(1, 'SCONST', 'x', 0)));
    }

    public function testUnescapeKeepsATrailingBackslash(): void
    {
        self::assertSame('end\\', (new Subject())->unescape('end\\'));
        self::assertSame('plain', (new Subject())->unescape('plain'));
    }
}
