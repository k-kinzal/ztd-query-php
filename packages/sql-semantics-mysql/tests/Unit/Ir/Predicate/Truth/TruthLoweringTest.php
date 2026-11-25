<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Truth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(TruthLowering::class)]
#[Small]
final class TruthLoweringTest extends TestCase
{
    public function testLowerRetainsTheResolvedWidthReferenceInsideNestedTruth(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context(['CREATE TABLE app.ledger (amount BIGINT)']);
        $result = $semantics->analyze('SELECT ((ledger.amount IS NOT UNKNOWN) IS TRUE) AS outcome FROM app.ledger', $context);
        self::assertInstanceOf(Select::class, $result);
        $outer = $result->columns[0]->expression;
        self::assertInstanceOf(TruthTest::class, $outer);
        self::assertSame(TruthOperator::True, $outer->operator);
        self::assertInstanceOf(TruthTest::class, $outer->operand);
        self::assertSame(TruthOperator::NotUnknown, $outer->operand->operator);
        self::assertInstanceOf(IntegralColumnReference::class, $outer->operand->operand);
        self::assertSame(0, $outer->operand->operand->slot);
    }
}
