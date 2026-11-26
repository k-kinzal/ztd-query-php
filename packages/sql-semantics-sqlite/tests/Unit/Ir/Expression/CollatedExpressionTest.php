<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;

#[CoversClass(CollatedExpression::class)]
#[Small]
final class CollatedExpressionTest extends TestCase
{
    public function testRetainsTheOperandAndExplicitCollation(): void
    {
        $operand = new TextLiteral('a ');
        $value = new CollatedExpression($operand, Collation::RightTrim);

        self::assertSame($operand, $value->operand);
        self::assertSame(Collation::RightTrim, $value->collation);
    }
}
