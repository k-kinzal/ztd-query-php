<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;

#[CoversClass(E\Rendering\SqlitePrecedence::class)]
#[Small]
final class SqlitePrecedenceTest extends TestCase
{
    public function testBinaryKeepsMultiplicationAboveAddition(): void
    {
        $precedence = new E\Rendering\SqlitePrecedence();
        self::assertGreaterThan($precedence->binary(E\SqliteBinaryOperator::Add), $precedence->binary(E\SqliteBinaryOperator::Multiply));
    }

    public function testExpressionTreatsExplicitGroupingAsAtomic(): void
    {
        $sum = new C\Expression\BinaryInput(new E\NullConstant(), E\SqliteBinaryOperator::Add, new E\NullConstant());
        $precedence = new E\Rendering\SqlitePrecedence();
        self::assertGreaterThan($precedence->expression($sum), $precedence->expression(new C\Expression\GroupedInput($sum)));
    }

    public function testGroupedProtectsTheRightChildOfALeftAssociativeOperation(): void
    {
        $inner = new C\Expression\BinaryInput(new E\NullConstant(), E\SqliteBinaryOperator::Subtract, new E\NullConstant());
        $precedence = new E\Rendering\SqlitePrecedence();
        self::assertFalse($precedence->grouped($inner, E\SqliteBinaryOperator::Subtract, false));
        self::assertTrue($precedence->grouped($inner, E\SqliteBinaryOperator::Subtract, true));
    }
}
