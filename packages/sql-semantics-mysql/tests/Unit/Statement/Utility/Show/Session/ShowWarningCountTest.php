<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarningCount;

#[CoversClass(ShowWarningCount::class)]
#[Medium]
final class ShowWarningCountTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW COUNT(*) WARNINGS');
        self::assertInstanceOf(ShowWarningCount::class, $show->statement);
        self::assertSame('@@session.warning_count', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW COUNT(*) WARNINGS', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW COUNT(*) WARNINGS')->toString());
    }
}
