<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\Lexer\LexerState;

#[CoversClass(LexerState::class)]
#[Small]
final class LexerStateTest extends TestCase
{
    public function testCases(): void
    {
        self::assertCount(6, LexerState::cases());
        self::assertSame('Hostname', LexerState::Hostname->name);
    }
}
