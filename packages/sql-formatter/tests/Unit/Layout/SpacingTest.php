<?php

declare(strict_types=1);

namespace Tests\Unit\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Layout\Spacing;
use SqlParser\Lexer\Token;

#[CoversClass(Spacing::class)]
final class SpacingTest extends TestCase
{
    #[TestWith(['a', ',', false, ''])]
    #[TestWith(['a', '.', false, ''])]
    #[TestWith(['.', 'b', false, ''])]
    #[TestWith(['(', '1', false, ''])]
    #[TestWith(['1', ')', false, ''])]
    #[TestWith(['-', '1', true, ''])]
    #[TestWith(['-', '-', true, ' '])]
    #[TestWith(['a', '+', false, ' '])]
    public function testBetweenSeparatesTokens(string $before, string $after, bool $unary, string $expected): void
    {
        self::assertSame($expected, Spacing::between(new Token(1, 'TOKEN', $before, 0), new Token(1, 'TOKEN', $after, 5), $unary));
    }

    public function testBetweenPreservesFunctionAdjacency(): void
    {
        $name = new Token(1, 'IDENT', 'count', 0);
        self::assertSame('', Spacing::between($name, new Token(2, 'LP', '(', 5)));
        self::assertSame(' ', Spacing::between($name, new Token(2, 'LP', '(', 6, ' ')));
        self::assertSame('', Spacing::between(null, $name));
    }
}
