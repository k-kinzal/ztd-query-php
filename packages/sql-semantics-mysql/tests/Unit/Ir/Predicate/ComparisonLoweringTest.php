<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\ComparisonLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ComparisonLowering::class)]
#[Small]
final class ComparisonLoweringTest extends TestCase
{
    public function testLowerKeepsTheNullSafeOperatorAndWideColumn(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.d (a BIGINT)']);
        $value = $semantics->analyze('SELECT a <=> NULL AS same FROM app.d', $context);
        self::assertInstanceOf(Select::class, $value);
        $comparison = $value->columns[0]->expression;
        self::assertInstanceOf(IntegerComparison::class, $comparison);
        self::assertSame(IntegerOperator::NullSafeEqual, $comparison->operator);
        self::assertInstanceOf(IntegralColumnReference::class, $comparison->left);
        self::assertInstanceOf(NullLiteral::class, $comparison->right);
    }

    public function testOperandKeepsTheNarrowLiteralSpelling(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');
        $result = (new ComparisonLowering())->operand(new NumberLiteral('0002'), new SourceBinding($operation->binding(), null));
        self::assertInstanceOf(IntegerLiteral::class, $result);
        self::assertSame('0002', $result->toString());
    }
}
