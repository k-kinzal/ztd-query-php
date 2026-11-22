<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCastLowering;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SignedCastLowering::class)]
#[Small]
final class SignedCastLoweringTest extends TestCase
{
    public function testLowerPreservesNestedSignedConversion(): void
    {
        $operation = (new Inspection())->inspect('SELECT CONVERT(CAST(-7 AS SIGNED), SIGNED INTEGER)');
        self::assertInstanceOf(Select::class, $operation->statement);
        $result = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $result);

        $value = (new SignedCastLowering())->lower($result->expression, new SourceBinding($operation->binding(), null));

        self::assertInstanceOf(SignedCast::class, $value);
        self::assertInstanceOf(SignedCast::class, $value->operand);
        self::assertInstanceOf(IntegerLiteral::class, $value->operand->operand);
        self::assertSame('-7', $value->operand->operand->decimal);
    }
}
