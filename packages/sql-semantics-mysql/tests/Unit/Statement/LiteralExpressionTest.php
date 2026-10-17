<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\LiteralExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\SignalItem;

#[CoversNothing]
#[Small]
final class LiteralExpressionTest extends TestCase
{
    public function testALiteralExpressionCanBeAConditionInformationOperand(): void
    {
        $literal = new NumberLiteral('1001');
        $item = new SignalItem(ConditionItemName::MysqlErrno, $literal);

        self::assertInstanceOf(LiteralExpression::class, $item->value);
        self::assertSame($literal, $item->value);
        self::assertNotContains(LiteralExpression::class, class_implements(SignedLiteral::class));
    }
}
