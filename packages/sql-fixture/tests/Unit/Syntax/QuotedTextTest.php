<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Syntax\QuotedText as Subject;

#[CoversClass(Subject::class)]
final class QuotedTextTest extends TestCase
{
    #[DataProvider('providerLexemes')]
    public function testUnquoteStripsDelimitersAndUnfoldsDoubledOnes(string $text, string $expected): void
    {
        self::assertSame($expected, (new Subject())->unquote($text));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerLexemes(): array
    {
        return [
            ["'a''b'", "a'b"],
            ['"q""r"', 'q"r'],
            ['`b``t`', 'b`t'],
            ['[br]', 'br'],
            ["''", ''],
            ['bare', 'bare'],
            ["'", "'"],
            ["'open", "'open"],
            ['', ''],
        ];
    }
}
