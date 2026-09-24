<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Transformation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Maintenance\ReindexOptions;
use SqlSemantics\Model\Transformation\RebuiltOperands;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(RebuiltOperands::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RebuiltOperandsTest extends TestCase
{
    public function testFindReturnsNullForAnUnknownOperand(): void
    {
        $operands = new RebuiltOperands();
        self::assertNull($operands->find(Expression::literal(1, Dialect::Sqlite)));
    }

    public function testRememberReturnsTheReplacementThatFindLaterResolves(): void
    {
        $operands = new RebuiltOperands();
        $original = Expression::literal(1, Dialect::Sqlite);
        $replacement = Expression::literal(2, Dialect::Sqlite);
        self::assertSame($replacement, $operands->remember($original, $replacement));
        self::assertSame($replacement, $operands->find($original));
        self::assertNull($operands->find($replacement));
    }

    public function testFindRejectsAReplacementOfAnotherSemanticType(): void
    {
        $operands = new RebuiltOperands();
        $original = Expression::literal(1, Dialect::Sqlite);
        $operands->remember($original, new ReindexOptions());
        $this->expectException(InvalidStructure::class);
        $operands->find($original);
    }

    public function testRememberKeepsEachTransformationIndependent(): void
    {
        $original = Expression::literal(1, Dialect::Sqlite);
        $first = new RebuiltOperands();
        $first->remember($original, Expression::literal(2, Dialect::Sqlite));
        self::assertNull((new RebuiltOperands())->find($original));
    }
}
