<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Flush;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem;
use SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushOption;

#[CoversClass(FlushItem::class)]
#[Medium]
final class FlushItemTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        self::assertSame('FLUSH ERROR LOGS, ENGINE LOGS, GENERAL LOGS, SLOW LOGS, LOGS, STATUS', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('flush error logs, engine logs, general logs, slow logs, logs, status')->toString());
    }

    public function testChannelIsNullByDefault(): void
    {
        self::assertNull((new FlushItem(FlushOption::Logs))->channel);
    }
}
