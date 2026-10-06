<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrorCount;

#[CoversClass(ShowErrorCount::class)]
#[Medium]
final class ShowErrorCountTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW COUNT(*) ERRORS');
        self::assertInstanceOf(ShowErrorCount::class, $show->statement);
        self::assertSame('@@session.error_count', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW COUNT(*) ERRORS', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW COUNT(*) ERRORS')->toString());
    }
}
