<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\Lexer\Charsets;

#[CoversClass(Charsets::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class CharsetsTest extends TestCase
{
    public function testHas(): void
    {
        self::assertTrue(Charsets::has('utf8mb4'));
        self::assertTrue(Charsets::has('LATIN1'));
        self::assertFalse(Charsets::has('klingon'));
    }
}
