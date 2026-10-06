<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\CoalescePartition;

#[CoversClass(CoalescePartition::class)]
#[Medium]
final class CoalescePartitionTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t COALESCE PARTITION 1')->facts->diagnostics);
    }

    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER TABLE t COALESCE PARTITION NO_WRITE_TO_BINLOG 3', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t COALESCE PARTITION LOCAL 3')->toString());
    }
}
