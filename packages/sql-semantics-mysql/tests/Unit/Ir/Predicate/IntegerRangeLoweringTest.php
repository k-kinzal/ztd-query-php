<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRangeLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IntegerRangeLowering::class)]
#[Small]
final class IntegerRangeLoweringTest extends TestCase
{
    public function testLowerResolvesNegationAndOrderedBounds(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 WHERE 2 NOT BETWEEN 9223372036854775807 AND NULL');
        self::assertInstanceOf(Select::class, $operation->statement);
        $parsed = $operation->statement->where;
        self::assertInstanceOf(Between::class, $parsed);

        $range = (new IntegerRangeLowering())->lower($parsed, new SourceBinding($operation->binding(), null));
        self::assertTrue($range->negated);
        self::assertInstanceOf(RangeIntegerLiteral::class, $range->lower);
        self::assertSame('9223372036854775807', $range->lower->decimal);
        self::assertInstanceOf(NullLiteral::class, $range->upper);
    }

    public function testOperandKeepsA64BitRangeConstant(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1');
        $operand = (new IntegerRangeLowering())->operand(
            new NumberLiteral('9007199254740993'),
            new SourceBinding($operation->binding(), null),
        );
        self::assertInstanceOf(RangeIntegerLiteral::class, $operand);
        self::assertSame('9007199254740993', $operand->decimal);
    }
}
