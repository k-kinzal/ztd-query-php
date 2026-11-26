<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Literal\IntrinsicIntegerName;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Ordering\ProjectedIntegerLowering;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(ProjectedIntegerLowering::class)]
#[Medium]
final class ProjectedIntegerLoweringTest extends TestCase
{
    public function testLowerPublishesTheExactWideInteger(): void
    {
        $value = (new Semantics())->analyze('SELECT 9223372036854775807 AS result');

        self::assertInstanceOf(Select::class, $value);
        self::assertInstanceOf(ProjectedIntegerLiteral::class, $value->columns[0]->expression);
        self::assertSame('9223372036854775807', $value->columns[0]->expression->decimal());
    }

    public function testNameRetainsAnIntrinsicHeadingBeyondAliasCapacity(): void
    {
        $digits = str_repeat('0', 256) . '1';
        $value = (new Semantics())->analyze('SELECT ' . $digits);

        self::assertInstanceOf(Select::class, $value);
        self::assertInstanceOf(IntrinsicIntegerName::class, $value->columns[0]->name);
        self::assertSame($digits, $value->columns[0]->name->value);
        self::assertSame('SELECT ' . $digits, $value->toString());
    }
}
