<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;

#[CoversClass(ShowWarnings::class)]
#[Medium]
final class ShowWarningsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW WARNINGS LIMIT 1');
        self::assertInstanceOf(ShowWarnings::class, $show->statement);
        self::assertSame('Level', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW WARNINGS LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW WARNINGS LIMIT 1')->toString());
    }
}
