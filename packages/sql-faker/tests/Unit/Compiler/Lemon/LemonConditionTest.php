<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Compiler\Lemon\LemonCondition;

#[CoversClass(LemonCondition::class)]
#[Small]
final class LemonConditionTest extends TestCase
{
    public function testEvaluateUsesDeclaredNamesAndLemonOperatorGrouping(): void
    {
        $condition = new LemonCondition(['YES']);
        self::assertTrue($condition->evaluate('YES'));
        self::assertTrue($condition->evaluate('YES || YES'));
        self::assertFalse($condition->evaluate('NO || NO'));
        self::assertFalse($condition->evaluate('NO'));
        self::assertTrue($condition->evaluate('!NO && YES'));
        self::assertFalse($condition->evaluate('NO && YES || YES'));
        self::assertTrue($condition->evaluate('(NO && YES) || YES'));
        self::assertTrue($condition->evaluate('YES || NO && NO'));
        self::assertFalse($condition->evaluate('!(YES || NO)'));
    }

    public function testEvaluateRejectsUnparsedTrailingText(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected Lemon condition token: )');
        (new LemonCondition())->evaluate('YES)');
    }

    public function testEvaluateRejectsEmptyConditions(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Empty Lemon preprocessor condition.');
        (new LemonCondition())->evaluate(' ');
    }

    public function testExpressionStopsAtTheMatchingParenthesis(): void
    {
        $offset = 0;
        self::assertTrue((new LemonCondition(['YES']))->expression(['YES', '||', 'NO', ')'], $offset));
        self::assertSame(3, $offset);
    }

    public function testExpressionRejectsUnknownOperators(): void
    {
        $offset = 0;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Lemon condition operator: +');
        (new LemonCondition())->expression(['YES', '+', 'NO'], $offset);
    }

    public function testTermReadsNestedNegationAndAdvancesOverItsClosingParenthesis(): void
    {
        $offset = 0;
        self::assertTrue((new LemonCondition(['YES']))->term(['!', '(', '!', 'YES', ')'], $offset));
        self::assertSame(5, $offset);
    }

    public function testTermRejectsAnUnclosedGroup(): void
    {
        $offset = 0;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unclosed Lemon preprocessor condition.');
        (new LemonCondition())->term(['(', 'NO'], $offset);
    }

    public function testTermRejectsNonIdentifierInput(): void
    {
        $offset = 0;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Lemon preprocessor term: 0');
        (new LemonCondition())->term(['0'], $offset);
    }
}
