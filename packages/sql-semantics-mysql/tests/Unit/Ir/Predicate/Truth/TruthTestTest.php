<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Truth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\ResultColumn;
use SqlSemantics\Platform\MySql\Ir\Select;

#[CoversClass(TruthTest::class)]
#[Small]
final class TruthTestTest extends TestCase
{
    public function testRetainsTheExactOperandAndUnknownPolarity(): void
    {
        $null = new NullLiteral();
        $test = new TruthTest(TruthOperator::NotUnknown, $null);

        self::assertSame($null, $test->operand);
        self::assertSame(TruthOperator::NotUnknown, $test->operator);
    }

    public function testCheckedHostRejectsAReferenceInsideNestedTruth(): void
    {
        $inside = new TruthTest(TruthOperator::Unknown, new IntegralColumnReference(0));
        $checked = new TruthTest(TruthOperator::False, $inside);
        $this->expectExceptionMessage('A predicate slot must belong to the SELECT source.');

        new Select([new ResultColumn($checked, new Identifier('outcome'))]);
    }
}
