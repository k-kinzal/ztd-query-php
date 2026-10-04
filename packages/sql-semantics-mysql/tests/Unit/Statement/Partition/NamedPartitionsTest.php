<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Partition\NamedPartitions;

#[CoversClass(NamedPartitions::class)]
#[Medium]
final class NamedPartitionsTest extends TestCase
{
    public function testRenderWritesTheNames(): void
    {
        self::assertSame('ALTER TABLE t TRUNCATE PARTITION p0, p1', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t TRUNCATE PARTITION p0, p1')->toString());
    }
}
