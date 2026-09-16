<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\Lemon\LemonCondition;

#[CoversClass(LemonCondition::class)]
#[UsesClass(GrammarSourceException::class)]
#[Small]
final class LemonConditionTest extends TestCase
{
    public function testEvaluate(): void
    {
        $condition = new LemonCondition(['A', 'B']);

        self::assertTrue($condition->evaluate('A'));
        self::assertFalse($condition->evaluate('C'));
        self::assertTrue($condition->evaluate('!C && (A || C)'));
        self::assertFalse($condition->evaluate('A && !B'));
        self::assertTrue($condition->evaluate('C || A && B'));
    }

    public function testEvaluateRejectsAnEmptyCondition(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonCondition())->evaluate('   ');
    }

    public function testEvaluateRejectsTrailingTokens(): void
    {
        $this->expectException(GrammarSourceException::class);

        (new LemonCondition())->evaluate('A B');
    }

    public function testExpression(): void
    {
        $position = 0;

        self::assertTrue((new LemonCondition(['A']))->expression(['A', '||', 'B'], $position));
        self::assertSame(3, $position);
    }

    public function testTerm(): void
    {
        $position = 0;
        $condition = new LemonCondition(['A']);

        self::assertFalse($condition->term(['!', 'A'], $position));
        self::assertSame(2, $position);
    }

    public function testTermRejectsAnUnclosedParenthesis(): void
    {
        $position = 0;

        $this->expectException(GrammarSourceException::class);

        (new LemonCondition())->term(['(', 'A'], $position);
    }

    public function testTermRejectsAMissingName(): void
    {
        $position = 0;

        $this->expectException(GrammarSourceException::class);

        (new LemonCondition())->term(['&&'], $position);
    }
}
