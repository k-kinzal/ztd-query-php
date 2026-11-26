<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\Binding\ParameterLowering;
use SqlSemantics\Platform\MySql\Ir\Binding\ParameterTarget;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ParameterLowering::class)]
#[Medium]
final class ParameterLoweringTest extends TestCase
{
    public function testLowerFixesTheProjectedCastAndSlot(): void
    {
        $query = (new Inspection())->inspect('SELECT CAST(? AS SIGNED) AS a');
        self::assertInstanceOf(Select::class, $query->statement);
        $field = $query->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $field);

        $parameter = (new ParameterLowering())->lower($field->expression, 1);

        self::assertInstanceOf(Parameter::class, $parameter);
        self::assertSame(1, $parameter->position);
    }

    public function testLowerDistinguishesTheSignedAndUnsignedPositions(): void
    {
        $value = (new Semantics())->analyze('SELECT CAST(? AS SIGNED) AS s, CAST((?) AS UNSIGNED INTEGER) AS u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Ir\Select::class, $value);

        self::assertEquals(new Parameter(1, ParameterTarget::Signed), $value->columns[0]->expression);
        self::assertEquals(new Parameter(2, ParameterTarget::Unsigned), $value->columns[1]->expression);
    }
}
