<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\When;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(When::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class WhenTest extends TestCase
{
    public function testPairsOneTestWithOneResult(): void
    {
        $test = Expression::literal(true, Dialect::PostgreSql);
        $result = Expression::literal(1, Dialect::PostgreSql);
        $branch = new When($test, $result);
        self::assertSame($test, $branch->test);
        self::assertSame($result, $branch->result);
    }

    public function testRejectsOperandsFromDifferentDialects(): void
    {
        $this->expectException(InvalidStructure::class);
        new When(Expression::literal(true, Dialect::PostgreSql), Expression::literal(1, Dialect::MySql));
    }
}
