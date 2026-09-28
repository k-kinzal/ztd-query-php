<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Core\Literal\Quoted;

#[CoversClass(Quoted::class)]
#[Medium]
final class QuotedTest extends TestCase
{
    #[TestWith(["'a''b'", false, "a'b"])]
    #[TestWith(["'a'\n-- 'ignored'\n'b'", false, 'ab'])]
    #[TestWith(["'a\\'b'", true, "a\\'b"])]
    public function testBodyUnquotesCompleteLexicalTokens(string $text, bool $backslash, string $expected): void
    {
        self::assertSame($expected, Quoted::body($text, $backslash));
    }
    #[TestWith(['abc'])]
    #[TestWith(["'abc"])]
    #[TestWith(["'abc' junk 'def'"])]
    public function testBodyRejectsIncompleteOrUnconsumedText(string $text): void
    {
        $this->expectException(DecodingException::class);
        Quoted::body($text);
    }

}
