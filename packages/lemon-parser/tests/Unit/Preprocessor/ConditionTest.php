<?php

declare(strict_types=1);

namespace Tests\Unit\Preprocessor;

use LemonParser\Ast\Location;
use LemonParser\Preprocessor\Condition;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Condition::class)]
#[UsesClass(Location::class)]
#[UsesClass(SyntaxException::class)]
#[Small]
final class ConditionTest extends TestCase
{
    public function testEvaluate(): void
    {
        $condition = new Condition(['A', 'B_2']);
        $at = new Location(1, 1);

        self::assertTrue($condition->evaluate('A', $at));
        self::assertFalse($condition->evaluate('C', $at));
        self::assertTrue($condition->evaluate(' !C ', $at));
        self::assertTrue($condition->evaluate('A || C', $at));
        self::assertFalse($condition->evaluate('A && C', $at));
        self::assertTrue($condition->evaluate('C || B_2 && A', $at));
        self::assertFalse($condition->evaluate('C || (B_2 && !A)', $at));
        self::assertTrue($condition->evaluate('!(C || D)', $at));
        self::assertFalse($condition->evaluate('', $at));
    }

    public function testEvaluateReadsLeftToRightWithoutPrecedence(): void
    {
        $condition = new Condition(['A', 'B']);

        self::assertFalse($condition->evaluate('C && A || B', new Location(1, 1)));
    }

    public function testEvaluateRejectsAnOperatorWithoutALeftSide(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: | <-- syntax error here at 2:1');

        (new Condition([]))->evaluate('|| A', new Location(2, 1));
    }

    public function testEvaluateRejectsASingleBar(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: A | <-- syntax error here at 2:1');

        (new Condition(['A']))->evaluate('A | B', new Location(2, 1));
    }

    public function testEvaluateRejectsASingleAmpersand(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: A & <-- syntax error here at 2:1');

        (new Condition(['A']))->evaluate('A & B', new Location(2, 1));
    }

    public function testEvaluateRejectsANegationAfterATerm(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: A ! <-- syntax error here at 2:1');

        (new Condition(['A']))->evaluate('A !B', new Location(2, 1));
    }

    public function testEvaluateRejectsAnAndWithoutALeftSide(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: & <-- syntax error here at 2:1');

        (new Condition(['A']))->evaluate('&& A', new Location(2, 1));
    }

    public function testEvaluateRejectsTwoTermsInARow(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: A B <-- syntax error here at 2:1');

        (new Condition([]))->evaluate('A B', new Location(2, 1));
    }

    public function testTerm(): void
    {
        $condition = new Condition(['A']);

        self::assertSame([true, 0], $condition->term('A', 0, new Location(1, 1)));
        self::assertSame([false, 6], $condition->term('x Bcd_1', 2, new Location(1, 1)));
        self::assertSame([true, 3], $condition->term('(!B)', 0, new Location(1, 1)));
    }

    public function testTermRejectsAnUnclosedGroup(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: (A <-- syntax error here at 1:1');

        (new Condition([]))->term('(A', 0, new Location(1, 1));
    }

    public function testTermRejectsANameStartingWithADigit(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: 1 <-- syntax error here at 1:1');

        (new Condition([]))->term('1A', 0, new Location(1, 1));
    }

    public function testGuard(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('%if syntax error: A & <-- syntax error here at 1:1');

        (new Condition([]))->guard(false, 'A && B', 2, new Location(1, 1));
    }

    public function testError(): void
    {
        $error = (new Condition([]))->error('A ? B', 2, new Location(5, 1));

        self::assertSame('%if syntax error: A ? <-- syntax error here at 5:1', $error->getMessage());
    }
}
