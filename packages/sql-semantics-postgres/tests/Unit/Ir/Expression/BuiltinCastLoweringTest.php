<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCastLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(BuiltinCastLowering::class)]
#[Small]
final class BuiltinCastLoweringTest extends TestCase
{
    public function testLowerResolvesTheNestedInputAndOutputTypes(): void
    {
        $operation = (new Inspection())->inspect('SELECT CAST((TRUE) AS INTEGER)::pg_catalog.bool');
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(Cast::class, $target->expression);
        $value = (new BuiltinCastLowering())->lower($target->expression, new SourceBinding($operation->binding(), null), true);

        self::assertInstanceOf(BuiltinCast::class, $value);
        self::assertSame(CastDestination::Boolean, $value->target);
        self::assertInstanceOf(BuiltinCast::class, $value->operand);
        self::assertSame(CastDestination::Integer, $value->operand->target);
        self::assertInstanceOf(BooleanLiteral::class, $value->operand->operand);
        self::assertTrue($value->operand->operand->value);
    }

    public function testOperandKeepsTheNativeSignedWidth(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE public.bounds (low bigint)');
        $operation = $inspection->inspect('SELECT CAST(low AS integer) FROM public.bounds', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        $target = $operation->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $target);
        self::assertInstanceOf(Cast::class, $target->expression);
        $binding = new SourceBinding($operation->binding(), $operation->statement->from);

        $operand = (new BuiltinCastLowering())->operand($target->expression->operand, $binding, true);
        self::assertInstanceOf(WideIntegerColumnReference::class, $operand);
        self::assertSame(0, $operand->slot);
    }
}
