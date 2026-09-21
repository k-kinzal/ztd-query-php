<?php

declare(strict_types=1);

namespace Tests\Unit\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\SourcePosition;

#[CoversClass(SourcePosition::class)]
#[Small]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class SourcePositionTest extends TestCase
{
    public function testAt(): void
    {
        $position = SourcePosition::at("SELECT 1\nFROM t", 12);

        self::assertSame(2, $position->line);
        self::assertSame(4, $position->column);
    }

    public function testAtTheStartAndPastTheEnd(): void
    {
        self::assertSame([1, 1], [SourcePosition::at('SELECT', 0)->line, SourcePosition::at('SELECT', 0)->column]);
        self::assertSame([1, 7], [SourcePosition::at('SELECT', 99)->line, SourcePosition::at('SELECT', 99)->column]);
        self::assertSame(1, SourcePosition::at('SELECT', -5)->column);
    }
}
