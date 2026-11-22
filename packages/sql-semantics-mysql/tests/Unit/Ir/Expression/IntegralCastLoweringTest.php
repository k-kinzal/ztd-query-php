<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\IntegralCastLowering;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IntegralCastLowering::class)]
#[Small]
final class IntegralCastLoweringTest extends TestCase
{
    public function testLowerPreservesNestedSignedConversion(): void
    {
        $operation = (new Inspection())->inspect('SELECT CONVERT(CAST(-7 AS SIGNED), SIGNED INTEGER)');
        self::assertInstanceOf(Select::class, $operation->statement);
        $result = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $value = (new IntegralCastLowering())->lower($result->expression, new SourceBinding($operation->binding(), null));

        self::assertInstanceOf(SignedCast::class, $value);
        self::assertInstanceOf(SignedCast::class, $value->operand);
        self::assertInstanceOf(IntegerLiteral::class, $value->operand->operand);
        self::assertSame('-7', $value->operand->operand->decimal);
    }

    public function testLowerRetainsBothIntegralConversionKinds(): void
    {
        $operation = (new Inspection())->inspect('SELECT CAST(CONVERT(-1, UNSIGNED) AS SIGNED)');
        self::assertInstanceOf(Select::class, $operation->statement);
        $result = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $value = (new IntegralCastLowering())->lower($result->expression, new SourceBinding($operation->binding(), null));

        self::assertInstanceOf(SignedCast::class, $value);
        self::assertInstanceOf(UnsignedCast::class, $value->operand);
        self::assertInstanceOf(IntegerLiteral::class, $value->operand->operand);
        self::assertSame('-1', $value->operand->operand->decimal);
    }
}
