<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Condition\ConditionDiagnostic;
use SqlSemantics\Model\Configuration\Condition\ConditionItem;

#[CoversClass(ConditionDiagnostic::class)]
final class ConditionDiagnosticTest extends TestCase
{
    public function testKeepsTheTargetAndItem(): void
    {
        $diagnostic = new ConditionDiagnostic('state', ConditionItem::ReturnedSqlState);
        self::assertSame(['state', ConditionItem::ReturnedSqlState], [$diagnostic->variable, $diagnostic->item]);
    }
}
