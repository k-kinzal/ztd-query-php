<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\RenderException;

#[CoversClass(RenderException::class)]
#[UsesClass(Token::class)]
#[Small]
final class RenderExceptionTest extends TestCase
{
    public function testUnseparableNamesBothTokens(): void
    {
        $left = new Token(1, 'ID', 'a', 0);
        $right = new Token(2, 'INTEGER', '1', 2);
        $exception = RenderException::unseparable($left, $right);

        self::assertSame('Cannot write ID and INTEGER next to each other', $exception->getMessage());
        self::assertSame($left, $exception->left);
        self::assertSame($right, $exception->right);
    }

    public function testUnreadableNamesTheTokenItEndedAt(): void
    {
        $last = new Token(1, 'ID', 'a', 0);
        $exception = RenderException::unreadable($last);

        self::assertSame('Text written from the tree does not read back as it, ending at ID', $exception->getMessage());
        self::assertSame($last, $exception->left);
        self::assertSame($last, $exception->right);
    }
}
