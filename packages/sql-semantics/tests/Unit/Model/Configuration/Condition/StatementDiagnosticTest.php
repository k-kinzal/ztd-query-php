<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Condition\StatementDiagnostic;
use SqlSemantics\Model\Configuration\Condition\StatementItem;

#[CoversClass(StatementDiagnostic::class)]
final class StatementDiagnosticTest extends TestCase
{
    public function testKeepsTheTargetAndItem(): void
    {
        $diagnostic = new StatementDiagnostic('rows', StatementItem::RowCount);
        self::assertSame(['rows', StatementItem::RowCount], [$diagnostic->variable, $diagnostic->item]);
    }
}
