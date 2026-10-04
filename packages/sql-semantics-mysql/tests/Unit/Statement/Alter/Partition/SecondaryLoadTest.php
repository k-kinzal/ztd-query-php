<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\SecondaryLoad;

#[CoversClass(SecondaryLoad::class)]
#[Medium]
final class SecondaryLoadTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t SECONDARY_UNLOAD')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t SECONDARY_LOAD PARTITION (p0, p1)', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('ALTER TABLE t SECONDARY_LOAD PARTITION (p0, p1)')->toString());
    }
}
