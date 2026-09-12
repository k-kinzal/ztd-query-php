<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Partition\ValueShape;

#[CoversClass(ValueShape::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ValueShapeTest extends TestCase
{
    #[DataProvider('providerShapes')]
    public function testResizePreservesExpressionsWhileMatchingTheRequiredWidth(string $input, bool $list, int $width, string $expected): void
    {
        $tokens = TerminalSequence::fromNames(explode(' ', $input))->terminals;
        $result = (new ValueShape())->resize($tokens, $list, $width);
        self::assertSame(explode(' ', $expected), array_column($result, 'name'));
        $retained = array_values(array_filter($result, static fn (TerminalOccurrence $token): bool => $token->id !== PHP_INT_MIN));
        self::assertSame(array_map(static fn (TerminalOccurrence $token): TerminalOccurrence => $tokens[$token->id], $retained), $retained);
    }

    /**
     * @return list<array{string, bool, int, string}>
     */
    public static function providerShapes(): array
    {
        return [
            ['MAX_VALUE_SYM', false, 1, 'MAX_VALUE_SYM'],
            ['MAX_VALUE_SYM', false, 3, '( MAX_VALUE_SYM , MAX_VALUE_SYM , MAX_VALUE_SYM )'],
            ['( NUM , NULL_SYM )', false, 1, '( NUM )'],
            ['( NUM )', false, 2, '( NUM , NUM )'],
            ['( NUM , NULL_SYM )', true, 1, '( NUM , NULL_SYM )'],
            ['( ( NUM ) , ( NULL_SYM , NUM ) )', true, 1, '( NUM , NULL_SYM , NUM )'],
            ['( ( NUM ) , ( NULL_SYM , NUM , NUM ) )', true, 2, '( ( NUM , NUM ) , ( NULL_SYM , NUM ) )'],
            ['( NUM , NULL_SYM )', true, 2, '( ( NUM , NUM ) , ( NULL_SYM , NUM ) )'],
            ['( + ( NUM + NUM ) , NUM )', true, 2, '( ( + ( NUM + NUM ) , NUM ) , ( NUM , NUM ) )'],
        ];
    }

    public function testSplitAndJoinHandleEmptyListsAndNestedCommas(): void
    {
        $shape = new ValueShape();
        self::assertSame([], $shape->split([]));
        self::assertSame([], $shape->join([], false));
        $tokens = TerminalSequence::fromNames(['NUM', ',', '(', 'NUM', ',', 'NUM', ')'])->terminals;
        self::assertSame([[$tokens[0]], array_slice($tokens, 2)], $shape->split($tokens));
    }

    public function testRowsDistinguishesScalarListsAndTuples(): void
    {
        $tokens = TerminalSequence::fromNames(['(', 'NUM', ',', 'NULL_SYM', ')'])->terminals;
        self::assertSame([[[$tokens[1]], [$tokens[3]]]], (new ValueShape())->rows($tokens, false));
        self::assertSame([[[$tokens[1]]], [[$tokens[3]]]], (new ValueShape())->rows($tokens, true));
    }

    public function testJoinUsesNewPunctuationAndRetainsValueIdentity(): void
    {
        $value = new TerminalOccurrence('NUM', 7);
        $joined = (new ValueShape())->join([[$value], [$value]], true);
        self::assertSame(['(', 'NUM', ',', 'NUM', ')'], array_column($joined, 'name'));
        self::assertSame($value, $joined[1]);
        self::assertSame($value, $joined[3]);
    }

    public function testTokenReservesAnIdentityOutsideTheDerivation(): void
    {
        $token = (new ValueShape())->token(',');
        self::assertSame(',', $token->name);
        self::assertSame(PHP_INT_MIN, $token->id);
    }
}
