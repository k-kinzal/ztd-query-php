<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;

#[CoversClass(ResourceLimit::class)]
#[Medium]
final class ResourceLimitTest extends TestCase
{
    public function testRenderWritesEachLimit(): void
    {
        self::assertSame("ALTER USER u WITH MAX_UPDATES_PER_HOUR 5 MAX_CONNECTIONS_PER_HOUR x'10'", (new Semantics(Dialect::MySql))->analyze('alter user u with max_updates_per_hour 5 max_connections_per_hour 0x10')->toString());
    }
}
