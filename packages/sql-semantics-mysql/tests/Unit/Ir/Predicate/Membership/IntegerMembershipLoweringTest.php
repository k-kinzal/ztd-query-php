<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Membership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Membership\IntegerMembershipLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IntegerMembershipLowering::class)]
#[Small]
final class IntegerMembershipLoweringTest extends TestCase
{
    public function testLowerRetainsAlternativeOrderAndSeparateNegation(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 2 NOT IN (9223372036854775807, NULL)');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(InList::class, $operation->statement->where);

        $test = (new IntegerMembershipLowering())->lower($operation->statement->where, new SourceBinding($operation->binding(), null));
        self::assertTrue($test->negated);
        self::assertInstanceOf(RangeIntegerLiteral::class, $test->alternatives[0]);
        self::assertSame('9223372036854775807', $test->alternatives[0]->decimal);
        self::assertInstanceOf(NullLiteral::class, $test->alternatives[1]);
    }

    public function testOperandReadsTheNativeLiteralSpelling(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');
        $operand = (new IntegerMembershipLowering())->operand(new NumberLiteral('0002'), new SourceBinding($operation->binding(), null));

        self::assertInstanceOf(RangeIntegerLiteral::class, $operand);
        self::assertSame('0002', $operand->toString());
    }
}
