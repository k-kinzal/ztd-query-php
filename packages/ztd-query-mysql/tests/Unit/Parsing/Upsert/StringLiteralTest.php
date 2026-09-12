<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Upsert\StringLiteral;

#[CoversClass(StringLiteral::class)]
final class StringLiteralTest extends TestCase
{
    public function testStringUnescapesDoubledQuotesAndBackslashes(): void
    {
        $reader = new StringLiteral();
        self::assertSame("a'b", $reader->string("'a''b'"));
        self::assertSame("a'b", $reader->string("'a\\'b'"));
        self::assertSame('a\\b', $reader->string("'a\\\\b'"));
        self::assertSame('', $reader->string("''"));
    }

}
