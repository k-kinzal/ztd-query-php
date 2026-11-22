<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastTarget;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;

#[CoversClass(CastExpression::class)]
#[Small]
final class CastExpressionTest extends TestCase
{
    public function testAffineValueRetainsItsCheckedOperand(): void
    {
        $integer = new IntegerLiteral('-7');
        $cast = new CastExpression(CastTarget::Text, $integer);

        self::assertSame($integer, $cast->operand);
        self::assertSame('(CAST(-7 AS TEXT))', (new SelectSql())->expression($cast, null));
    }
}
