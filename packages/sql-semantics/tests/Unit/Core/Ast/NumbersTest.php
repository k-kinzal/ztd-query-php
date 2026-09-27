<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlSemantics\Core\Ast\Numbers;

#[CoversClass(Numbers::class)]
#[Small]
final class NumbersTest extends TestCase
{
    /**
     * @param list<string> $texts
     */
    #[DataProvider('providerIntegers')]
    public function testIntegerReadsSignedDecimalsWithoutOverflow(array $texts, ?int $expected): void
    {
        self::assertSame($expected, Numbers::integer(array_map(static fn (string $text): Token => new Token(1, 'NUM', $text, 0), $texts)));
    }

    /**
     * @return iterable<string, array{list<string>, ?int}>
     */
    public static function providerIntegers(): iterable
    {
        yield 'plain' => [['42'], 42];
        yield 'zero' => [['0'], 0];
        yield 'leading zeros' => [['0007'], 7];
        yield 'negative' => [['-', '3'], -3];
        yield 'positive sign' => [['+', '3'], 3];
        yield 'underscores' => [['1_000'], 1000];
        yield 'max' => [['9223372036854775807'], PHP_INT_MAX];
        yield 'min' => [['-', '9223372036854775808'], PHP_INT_MIN];
        yield 'overflow' => [['9223372036854775808'], null];
        yield 'negative overflow' => [['-', '9223372036854775809'], null];
        yield 'float' => [['1.5'], null];
        yield 'word' => [['ten'], null];
        yield 'empty' => [[], null];
        yield 'expression' => [['1', '+', '2'], null];
    }

    public function testArgumentsSplitsAtTopLevelCommasOnly(): void
    {
        $tokens = array_map(static fn (string $text): Token => new Token(1, 'X', $text, 0), ['10', ',', 'f', '(', '1', ',', '2', ')', ',', '-', '3']);
        $arguments = Numbers::arguments($tokens);
        self::assertCount(3, $arguments);
        self::assertSame(['10'], array_column($arguments[0], 'text'));
        self::assertSame(['f', '(', '1', ',', '2', ')'], array_column($arguments[1], 'text'));
        self::assertSame(['-', '3'], array_column($arguments[2], 'text'));
        self::assertSame([], Numbers::arguments([]));
    }
}
