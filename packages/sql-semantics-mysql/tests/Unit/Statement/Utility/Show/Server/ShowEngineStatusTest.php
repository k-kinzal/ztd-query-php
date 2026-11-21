<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;

#[CoversClass(ShowEngineStatus::class)]
#[Medium]
final class ShowEngineStatusTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics())->analyze('SHOW ENGINE ALL STATUS');
        self::assertInstanceOf(ShowEngineStatus::class, $show->statement);
        self::assertSame('Type', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW ENGINE ALL STATUS', (new Semantics())->analyze('SHOW ENGINE ALL STATUS')->toString());
    }
}
