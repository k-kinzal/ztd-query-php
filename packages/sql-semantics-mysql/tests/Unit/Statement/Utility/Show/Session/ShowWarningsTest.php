<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowWarnings::class)]
#[Medium]
final class ShowWarningsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.6.51'))->inspect('SHOW WARNINGS LIMIT 1');
        self::assertInstanceOf(ShowWarnings::class, $show->statement);
        self::assertSame('Level', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW WARNINGS LIMIT 1', (new Inspection('mysql-5.6.51'))->inspect('SHOW WARNINGS LIMIT 1')->toString());
    }
}
