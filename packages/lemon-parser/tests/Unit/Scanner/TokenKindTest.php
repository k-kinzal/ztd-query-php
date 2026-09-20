<?php

declare(strict_types=1);

namespace Tests\Unit\Scanner;

use LemonParser\Scanner\TokenKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TokenKind::class)]
#[Small]
final class TokenKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['word', 'string', 'braced code', '::=', 'compound token', 'punctuation', 'end of file'], array_map(static fn (TokenKind $kind): string => $kind->value, TokenKind::cases()));
    }
}
