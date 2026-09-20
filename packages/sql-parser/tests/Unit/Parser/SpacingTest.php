<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Spacing;

#[CoversClass(Spacing::class)]
#[UsesClass(Token::class)]
#[Small]
final class SpacingTest extends TestCase
{
    #[DataProvider('providerNeighbours')]
    public function testSeparatesGuessesByThePunctuationBetweenTwoTokens(string $left, string $right, bool $expected): void
    {
        $leftToken = new Token(1, 'LEFT', $left, Token::DETACHED);
        $rightToken = new Token(2, 'RIGHT', $right, Token::DETACHED);

        self::assertSame($expected, (new Spacing())->separates($leftToken, $rightToken));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function providerNeighbours(): array
    {
        return [
            'two words' => ['SELECT', 'a', true],
            'before an opening bracket' => ['COUNT', '(', false],
            'after an opening bracket' => ['(', 'a', false],
            'before a closing bracket' => ['a', ')', false],
            'before a comma' => ['a', ',', false],
            'after a comma' => [',', 'a', true],
            'before a dot' => ['users', '.', false],
            'after a dot' => ['.', 'id', false],
            'before a semicolon' => ['a', ';', false],
            'after a sigil' => ['@', 'rows', false],
            'around an operator' => ['a', '=', true],
        ];
    }

    public function testSeparatesGuessesTheSameWhicheverTokensItIsGiven(): void
    {
        $spacing = new Spacing();
        $read = new Token(1, 'IDENT', 'a', 0, '  ');
        $built = new Token(2, 'IDENT', 'b', Token::DETACHED);

        self::assertTrue($spacing->separates($read, $built));
        self::assertTrue($spacing->separates($built, $read));
    }
}
