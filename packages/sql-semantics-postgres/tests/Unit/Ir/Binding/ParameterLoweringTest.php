<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\ParameterLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(ParameterLowering::class)]
#[Medium]
final class ParameterLoweringTest extends TestCase
{
    public function testTargetRecognizesTheWideBuiltin(): void
    {
        $query = (new Inspection())->inspect('SELECT CAST($1 AS bigint) AS a');
        self::assertInstanceOf(Select::class, $query->statement);
        $field = $query->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $field);
        self::assertInstanceOf(Cast::class, $field->expression);

        self::assertSame(CastDestination::WideInteger, (new ParameterLowering())->target($field->expression, $query->binding()->context));
    }

    public function testLowerFixesTheProjectedCastAndSlot(): void
    {
        $query = (new Inspection())->inspect('SELECT CAST($1 AS integer) AS a');
        self::assertInstanceOf(Select::class, $query->statement);
        $field = $query->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $field);

        $parameter = (new ParameterLowering())->lower($field->expression, $query->binding()->context);

        self::assertInstanceOf(Parameter::class, $parameter);
        self::assertSame(1, $parameter->position);
    }
}
