<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineLogs;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowEngineLogs::class)]
#[Medium]
final class ShowEngineLogsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.6.51'))->inspect('SHOW ENGINE ALL LOGS');
        self::assertInstanceOf(ShowEngineLogs::class, $show->statement);
        self::assertSame('Type', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW ENGINE ALL LOGS', (new Inspection('mysql-5.6.51'))->inspect('SHOW ENGINE ALL LOGS')->toString());
    }
}
