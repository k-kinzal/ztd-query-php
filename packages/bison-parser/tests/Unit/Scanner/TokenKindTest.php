<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use BisonParser\Scanner\TokenKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenKind::class)]
#[Small]
final class TokenKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertCount(22, TokenKind::cases());
        self::assertSame('%%', TokenKind::Section->value);
        self::assertSame('end of file', TokenKind::End->value);
    }
}
