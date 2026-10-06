<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\AllPartitions;

#[CoversClass(AllPartitions::class)]
#[Medium]
final class AllPartitionsTest extends TestCase
{
    public function testRenderWritesAll(): void
    {
        self::assertSame('ALTER TABLE t ANALYZE PARTITION ALL', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ANALYZE PARTITION all')->toString());
    }
}
