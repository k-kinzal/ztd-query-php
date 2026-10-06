<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(ThreadPriority::class)]
#[Medium]
final class ThreadPriorityTest extends TestCase
{
    public function testDescribeWritesTheSign(): void
    {
        self::assertSame('7', (new ThreadPriority(false, new Numeral('7')))->describe());
    }

    public function testRenderWritesTheSign(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g THREAD_PRIORITY = -5', (new Semantics(Dialect::MySql))->analyze('alter resource group g thread_priority = -5')->toString());
    }
}
